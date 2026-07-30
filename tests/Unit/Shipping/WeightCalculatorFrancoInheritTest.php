<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Cart;
use Lunar\Models\TaxClass;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Models\Supplier;
use Pko\ShippingCommon\Support\PortModeResolver;
use Pko\ShippingCommon\Support\WeightCalculator;
use Tests\TestCase;

/**
 * Couvre le bug L3 : franco silencieusement cassé pour les produits en mode 'inherit'.
 *
 * Chaîne testée : pko_port_mode='inherit' + fournisseur port_inclus + WeightCalculator::isFrancoEligible().
 */
class WeightCalculatorFrancoInheritTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TaxClass::query()->firstOrCreate(['name' => 'TVA 20%'], ['default' => true]);
        PortModeResolver::flushCache();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Produit objet avec les colonnes utilisées par WeightCalculator/PortModeResolver. */
    private function makeProduct(
        string $portMode,
        bool $francoEligible,
        ?int $supplierId = null,
    ): object {
        return (object) [
            'pko_port_mode' => $portMode,
            'pko_franco_eligible' => $francoEligible,
            'pko_supplier_id' => $supplierId,
        ];
    }

    /** Ligne de panier avec un sous-total HT en cents. */
    private function makeLine(object $product, int $subTotalCents = 10000): object
    {
        $subTotal = (object) ['value' => $subTotalCents];

        $variant = (object) [
            'weight_value' => 1.0,
            'weight_unit' => 'kg',
            'product' => $product,
        ];

        return (object) [
            'purchasable' => $variant,
            'quantity' => 1,
            'subTotal' => $subTotal,
        ];
    }

    /** @param  list<object>  $lines */
    private function makeCart(array $lines): Cart&MockInterface
    {
        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')
            ->with('lines')
            ->andReturn(new Collection($lines));

        return $cart;
    }

    // ── BLOQUANT-1 : cas régressif bout-en-bout ───────────────────────────────

    /**
     * Produit en mode 'inherit' + fournisseur port_inclus='non' (résout en 'standard')
     * => DOIT être éligible au franco dans WeightCalculator.
     *
     * État bugué : pko_franco_eligible=false persisté par l'UI → ligne exclue à tort.
     */
    public function test_inherit_supplier_non_est_eligible_franco(): void
    {
        $supplier = Supplier::create(['name' => 'Fournisseur NON', 'port_inclus' => 'non']);

        // Simule l'état réel en base après le bug UI (franco_eligible=false)
        $product = $this->makeProduct('inherit', false, $supplier->id);

        $cart = $this->makeCart([$this->makeLine($product, 15000)]);

        // Aucune ligne ne doit être exclue du franco
        $this->assertFalse(WeightCalculator::cartHasFrancoExcludedLine($cart));
        // Le sous-total éligible doit inclure la ligne
        $this->assertSame(15000, WeightCalculator::francoEligibleSubtotalHt($cart));
    }

    /**
     * Produit en mode 'inherit' + fournisseur port_inclus='oui' (résout en 'free')
     * => NON éligible au franco (port inclus = franco inutile).
     */
    public function test_inherit_supplier_oui_nest_pas_eligible_franco(): void
    {
        $supplier = Supplier::create(['name' => 'Fournisseur OUI', 'port_inclus' => 'oui']);
        $product = $this->makeProduct('inherit', false, $supplier->id);

        $cart = $this->makeCart([$this->makeLine($product, 15000)]);

        $this->assertTrue(WeightCalculator::cartHasFrancoExcludedLine($cart));
        $this->assertSame(0, WeightCalculator::francoEligibleSubtotalHt($cart));
    }

    /**
     * Produit en mode 'inherit' sans fournisseur (résout en 'standard' par défaut)
     * => éligible au franco.
     */
    public function test_inherit_sans_fournisseur_est_eligible_franco(): void
    {
        $product = $this->makeProduct('inherit', false, null);

        $cart = $this->makeCart([$this->makeLine($product, 8000)]);

        $this->assertFalse(WeightCalculator::cartHasFrancoExcludedLine($cart));
        $this->assertSame(8000, WeightCalculator::francoEligibleSubtotalHt($cart));
    }

    // ── Non-régression : les 4 autres modes ──────────────────────────────────

    /** Mode 'standard' avec pko_franco_eligible=true → éligible (comportement inchangé). */
    public function test_standard_eligible_est_eligible_franco(): void
    {
        $product = $this->makeProduct('standard', true);

        $cart = $this->makeCart([$this->makeLine($product, 20000)]);

        $this->assertFalse(WeightCalculator::cartHasFrancoExcludedLine($cart));
        $this->assertSame(20000, WeightCalculator::francoEligibleSubtotalHt($cart));
    }

    /** Mode 'free' → non éligible (port offert, franco sans objet). */
    public function test_free_nest_pas_eligible_franco(): void
    {
        $product = $this->makeProduct('free', false);

        $cart = $this->makeCart([$this->makeLine($product, 20000)]);

        $this->assertTrue(WeightCalculator::cartHasFrancoExcludedLine($cart));
        $this->assertSame(0, WeightCalculator::francoEligibleSubtotalHt($cart));
    }

    /** Mode 'flat' → non éligible (prix fixe défini sur le produit). */
    public function test_flat_nest_pas_eligible_franco(): void
    {
        $product = $this->makeProduct('flat', false);

        $cart = $this->makeCart([$this->makeLine($product, 20000)]);

        $this->assertTrue(WeightCalculator::cartHasFrancoExcludedLine($cart));
        $this->assertSame(0, WeightCalculator::francoEligibleSubtotalHt($cart));
    }

    /** Mode 'quote' → non éligible (prix transport inconnu). */
    public function test_quote_nest_pas_eligible_franco(): void
    {
        $product = $this->makeProduct('quote', false);

        $cart = $this->makeCart([$this->makeLine($product, 20000)]);

        $this->assertTrue(WeightCalculator::cartHasFrancoExcludedLine($cart));
        $this->assertSame(0, WeightCalculator::francoEligibleSubtotalHt($cart));
    }

    // ── BLOQUANT-2 : cache statique PortModeResolver ─────────────────────────

    /**
     * N appels resolve() sur le même supplier_id => 1 seule requête SQL.
     */
    public function test_resolver_cache_une_seule_requete_par_supplier(): void
    {
        $supplier = Supplier::create(['name' => 'Cache Test', 'port_inclus' => 'non']);
        $product = (object) ['pko_port_mode' => 'inherit', 'pko_supplier_id' => $supplier->id];

        DB::flushQueryLog();
        DB::enableQueryLog();

        for ($i = 0; $i < 5; $i++) {
            PortModeResolver::resolve($product);
        }

        $queries = DB::getQueryLog();
        $supplierQueries = array_filter(
            $queries,
            fn (array $q) => str_contains($q['query'], 'pko_suppliers'),
        );

        $this->assertCount(1, $supplierQueries, '5 appels resolve() sur le même supplier_id ne doivent générer qu\'1 requête SQL sur pko_suppliers.');
    }

    /**
     * flushCache() remet le compteur à zéro — deux suppliers différents génèrent chacun 1 requête,
     * et un deuxième appel après flush re-requête en base.
     */
    public function test_flush_cache_force_nouvelle_requete(): void
    {
        $supplier = Supplier::create(['name' => 'Flush Test', 'port_inclus' => 'oui']);
        $product = (object) ['pko_port_mode' => 'inherit', 'pko_supplier_id' => $supplier->id];

        // Première passe — met en cache
        PortModeResolver::resolve($product);

        PortModeResolver::flushCache();

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Après flush → doit re-requêter
        PortModeResolver::resolve($product);

        $queries = DB::getQueryLog();
        $supplierQueries = array_filter(
            $queries,
            fn (array $q) => str_contains($q['query'], 'pko_suppliers'),
        );

        $this->assertCount(1, $supplierQueries, 'Après flushCache(), resolve() doit re-requêter en base.');
    }
}
