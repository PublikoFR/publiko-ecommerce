<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Collection;
use Lunar\Base\ShippingManifestInterface;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Modifiers\FrancoModifier;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

/**
 * Tests de la base de calcul (eligible_only vs cart_total) et des services configurables.
 *
 * setUp() vide le cache settings pour éviter les fuites entre tests.
 */
class FrancoModifierBasisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeLine(
        bool $francoEligible,
        string $portMode = 'standard',
        int $subtotalHtCents = 30000,
    ): object {
        $product = (object) [
            'pko_franco_eligible' => $francoEligible,
            'pko_port_mode' => $portMode,
            'pko_supplier_id' => null,
        ];

        $variant = (object) [
            'weight_value' => 1.0,
            'weight_unit' => 'kg',
            'product' => $product,
        ];

        return (object) [
            'purchasable' => $variant,
            'quantity' => 1,
            'subTotal' => (object) ['value' => $subtotalHtCents],
        ];
    }

    private function makeCart(array $lines): Cart&MockInterface
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);

        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);
        $cart->shouldReceive('offsetExists')->andReturnUsing(
            fn ($key): bool => in_array($key, ['lines', 'currency'], true),
        );

        return $cart;
    }

    private function makePaidOption(string $identifier, int $priceCents = 990): ShippingOption
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $taxClass = TaxClass::make(['id' => 1, 'name' => 'Default', 'default' => true]);

        return new ShippingOption(
            name: 'Service '.$identifier,
            description: 'Description '.$identifier,
            identifier: $identifier,
            price: new Price($priceCents, $currency, 1),
            taxClass: $taxClass,
            meta: ['carrier' => 'chronopost', 'service_code' => explode('.', $identifier)[1] ?? $identifier],
        );
    }

    private function runModifier(Cart&MockInterface $cart, array $preloadOptions = []): Collection
    {
        ShippingManifest::clearOptions();

        foreach ($preloadOptions as $option) {
            ShippingManifest::addOption($option);
        }

        $modifier = new FrancoModifier;
        $modifier->handle($cart, fn ($c) => $c);

        return app(ShippingManifestInterface::class)->options;
    }

    // ── Tests basis = eligible_only (comportement par défaut) ────────────────

    public function test_eligible_only_applique_franco_si_panier_homogene_eligible(): void
    {
        // Défaut : eligible_only, pas de valeur DB → tombe sur config → 50 000 cents
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidOption('chronopost.chrono13'),
        ]);

        $chrono13 = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(0, $chrono13->price->value, 'Franco doit s\'appliquer sur panier homogène éligible');
    }

    public function test_eligible_only_bloque_si_ligne_exclue(): void
    {
        // Une ligne non éligible suffit à bloquer le franco en mode eligible_only
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 55000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 10000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(990, $chrono13->price->value, 'Franco bloqué par ligne exclue en mode eligible_only');
    }

    // ── Tests basis = cart_total ──────────────────────────────────────────────

    public function test_cart_total_applique_franco_meme_avec_ligne_exclue(): void
    {
        Setting::set('shipping.franco.basis', 'cart_total');

        // Total panier = 40 000 + 15 000 = 55 000 >= 50 000 → franco OK même avec ligne exclue
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 15000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(0, $chrono13->price->value, 'Franco doit s\'appliquer en cart_total même avec ligne exclue');
        $this->assertTrue($chrono13->meta['franco'] ?? false);
    }

    public function test_cart_total_ne_depasse_pas_seuil(): void
    {
        Setting::set('shipping.franco.basis', 'cart_total');
        Setting::set('shipping.franco.threshold_cents', 50000);

        // Total = 20 000 + 15 000 = 35 000 < 50 000 → pas de franco
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 15000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(990, $chrono13->price->value, 'Pas de franco si total cart_total < seuil');
    }

    // ── Tests services configurables ─────────────────────────────────────────

    public function test_franco_multi_services_rend_tous_gratuits(): void
    {
        Setting::set('shipping.franco.services', ['chrono13', 'chrono10']);
        Setting::set('shipping.franco.threshold_cents', 50000);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidOption('chronopost.chrono13', 990),
            $this->makePaidOption('chronopost.chrono10', 1490),
            $this->makePaidOption('chronopost.chrono_relais', 690),
        ]);

        $chrono13 = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $chrono10 = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono10');
        $relais   = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono_relais');

        $this->assertSame(0, $chrono13->price->value, 'chrono13 doit être gratuit');
        $this->assertSame(0, $chrono10->price->value, 'chrono10 doit être gratuit');
        $this->assertSame(690, $relais->price->value, 'chrono_relais doit rester payant');
    }

    public function test_service_inconnu_ignore_sans_planter(): void
    {
        Setting::set('shipping.franco.services', ['service_inexistant']);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
        ]);

        // Doit s'exécuter sans exception
        $options = $this->runModifier($cart, [
            $this->makePaidOption('chronopost.chrono13', 990),
        ]);

        $this->assertCount(1, $options);
        $this->assertSame(990, $options->first()->price->value, 'Option non ciblée doit rester inchangée');
    }

    // ── Test seuil configurable ───────────────────────────────────────────────

    public function test_seuil_db_gagne_sur_config(): void
    {
        Setting::set('shipping.franco.threshold_cents', 30000);

        // 35 000 >= 30 000 → franco doit s'appliquer (n'atteindrait pas 50 000 par défaut)
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 35000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(0, $chrono13->price->value, 'Franco doit s\'appliquer avec seuil DB = 30 000 cents');
    }
}
