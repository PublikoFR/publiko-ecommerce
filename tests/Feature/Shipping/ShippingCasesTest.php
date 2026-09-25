<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\PkoBrandSeeder;
use Database\Seeders\PkoChannelSeeder;
use Database\Seeders\PkoCollectionSeeder;
use Database\Seeders\PkoCountrySeeder;
use Database\Seeders\PkoCurrencySeeder;
use Database\Seeders\PkoCustomerGroupSeeder;
use Database\Seeders\PkoLanguageSeeder;
use Database\Seeders\PkoProductTypeSeeder;
use Database\Seeders\PkoShippingCasesProductSeeder;
use Database\Seeders\PkoShippingSurchargesSeeder;
use Database\Seeders\PkoSupplierSeeder;
use Database\Seeders\PkoTaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Exceptions\Carts\CartException;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\ProductVariant;
use Pko\ShippingCommon\Pricing\CalculatedShippingOption;
use Pko\ShippingCommon\Pricing\ShippingCalculator;
use Pko\ShippingCommon\Pricing\ShippingQuote;
use Pko\ShippingCommon\Support\PortModeResolver;
use Tests\TestCase;

/**
 * Scénarios de bout en bout du calcul de frais de port, joués sur le catalogue
 * de test seedé (`PkoShippingCasesProductSeeder`, SKU TX-*, cf. shipping.md §5.17).
 *
 * Contrairement à `Tests\Unit\Shipping\ShippingCalculatorTest` (lignes mockées,
 * client carrier mocké), ces tests utilisent :
 *   - les vrais produits seedés et leurs colonnes pko_* ;
 *   - un vrai panier Lunar (`Cart::add()`), donc les vrais sous-totaux HT ;
 *   - la vraie grille Chronopost en DB (mode GRID, aucun appel SOAP).
 *
 * Ils constituent la garde anti-régression du tableau de scénarios de la refonte
 * frais de port : toute modification de grille, de seuil ou de partition des
 * lignes casse ici en premier.
 */
class ShippingCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PortModeResolver::flushCache();

        $this->seed([
            PkoCurrencySeeder::class,
            PkoChannelSeeder::class,
            PkoLanguageSeeder::class,
            PkoCountrySeeder::class,
            PkoTaxSeeder::class,
            PkoCustomerGroupSeeder::class,
            PkoShippingSurchargesSeeder::class,
            PkoBrandSeeder::class,
            PkoCollectionSeeder::class,
            PkoProductTypeSeeder::class,
            PkoSupplierSeeder::class,
            PkoShippingCasesProductSeeder::class,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Construit un panier à partir d'une map SKU => quantité, avec adresse de livraison.
     *
     * @param  array<string, int>  $skus
     */
    private function cartFor(array $skus, string $postcode = '75001'): Cart
    {
        $currency = Currency::query()->where('default', true)->firstOrFail();
        $cart = Cart::factory()->create(['currency_id' => $currency->id]);
        CartSession::use($cart);

        foreach ($skus as $sku => $quantity) {
            $cart->add(
                ProductVariant::query()->where('sku', $sku)->firstOrFail(),
                $quantity,
            );
        }

        $cart->setShippingAddress([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'line_one' => '1 Rue de la Paix',
            'city' => 'Paris',
            'postcode' => $postcode,
            'country_id' => Country::query()->where('iso2', 'FR')->value('id'),
            'contact_email' => 'jean@example.com',
        ]);

        // calculate() peuple les sous-totaux HT des lignes — sans quoi le calcul
        // du franco verrait un panier à 0 €.
        return $cart->refresh()->calculate();
    }

    /**
     * @param  array<string, int>  $skus
     */
    private function quoteFor(array $skus, string $postcode = '75001'): ShippingQuote
    {
        return app(ShippingCalculator::class)->calculate($this->cartFor($skus, $postcode));
    }

    private function service(ShippingQuote $quote, string $serviceCode): ?CalculatedShippingOption
    {
        return collect($quote->options)->firstWhere('serviceCode', $serviceCode);
    }

    /**
     * @return list<string>
     */
    private function bannerTypes(ShippingQuote $quote): array
    {
        return array_values(array_map(fn (array $b): string => (string) $b['type'], $quote->banners));
    }

    // ── 1 à 3. Tranches de grille ────────────────────────────────────────────

    /**
     * Chrono 10 n'est pas au contrat Chronopost : sa ligne reste en base (grille,
     * historique) mais le service est désactivé et n'est plus proposé.
     */
    public function test_scenario_01_produit_leger_propose_les_deux_services_du_contrat(): void
    {
        $quote = $this->quoteFor(['TX-01' => 1]);

        $this->assertCount(2, $quote->options);
        $this->assertSame(1890, $this->service($quote, 'chrono13')?->totalPriceCents());
        $this->assertSame(1490, $this->service($quote, 'chrono_relais')?->totalPriceCents());
        $this->assertNull($this->service($quote, 'chrono10'));
    }

    public function test_scenario_02_borne_20kg_conserve_le_point_relais(): void
    {
        $quote = $this->quoteFor(['TX-04' => 1]); // 20,0 kg exactement

        $relais = $this->service($quote, 'chrono_relais');
        $this->assertNotNull($relais, 'Le bracket 20 kg doit être inclusif');
        $this->assertSame(3290, $relais->totalPriceCents());
        $this->assertSame(3990, $this->service($quote, 'chrono13')?->totalPriceCents());
        $this->assertTrue($relais->requiresPickupPoint);
    }

    public function test_scenario_03_au_dela_de_20kg_le_point_relais_disparait(): void
    {
        $quote = $this->quoteFor(['TX-05' => 1]); // 25 kg

        $this->assertNull($this->service($quote, 'chrono_relais'));
        $this->assertCount(1, $quote->options);
        $this->assertSame(5490, $this->service($quote, 'chrono13')?->totalPriceCents());
    }

    // ── 4. Hors grille → sur devis ───────────────────────────────────────────

    public function test_scenario_04_hors_grille_bascule_en_sur_devis(): void
    {
        $quote = $this->quoteFor(['TX-07' => 1]); // 35 kg

        $this->assertCount(1, $quote->options);
        $this->assertSame(ShippingCalculator::OVERWEIGHT_QUOTE_IDENTIFIER, $quote->options[0]->identifier);
        $this->assertTrue($quote->isQuoteOnly());
        $this->assertNotEmpty($quote->blockers);
    }

    // ── 5 à 7. Franco ────────────────────────────────────────────────────────

    /**
     * Règle métier : au-delà du seuil, le port est offert **quel que soit** le
     * service choisi (`shipping.franco.services` = `['*']` par défaut).
     */
    public function test_scenario_05_franco_offre_tous_les_services(): void
    {
        $quote = $this->quoteFor(['TX-08' => 1]); // 600 € HT

        foreach (['chrono13', 'chrono_relais'] as $serviceCode) {
            $option = $this->service($quote, $serviceCode);
            $this->assertTrue($option?->franco, "{$serviceCode} doit être offert");
            $this->assertSame(0, $option->totalPriceCents());
        }

        $this->assertContains('franco_reached', $this->bannerTypes($quote));
    }

    public function test_scenario_06_sous_le_seuil_affiche_le_reste_a_parcourir(): void
    {
        $quote = $this->quoteFor(['TX-02' => 3]); // 3 × 150 € = 450 € HT

        $this->assertFalse($this->service($quote, 'chrono13')?->franco);

        $progress = collect($quote->banners)->firstWhere('type', 'franco_progress');
        $this->assertNotNull($progress);
        $this->assertSame(5000, $progress['remaining_cents'], 'Il doit rester 50 € HT');
    }

    public function test_scenario_07_une_ligne_exclue_annule_le_franco(): void
    {
        $quote = $this->quoteFor(['TX-08' => 1, 'TX-09' => 1]); // 600 € + 550 € HT

        $this->assertFalse(
            $this->service($quote, 'chrono13')?->franco,
            'TX-09 est exclu du franco : le panier entier perd le franco',
        );
        $this->assertContains('excluded_lines', $this->bannerTypes($quote));
    }

    // ── 8 et 9. Mode flat ────────────────────────────────────────────────────

    public function test_scenario_08_flat_seul_liste_les_services_avec_le_forfait(): void
    {
        $quote = $this->quoteFor(['TX-10' => 1]); // forfait 25 € HT, 12 kg

        $this->assertCount(2, $quote->options);

        $chrono13 = $this->service($quote, 'chrono13');
        $this->assertSame(0, $chrono13?->gridPriceCents, 'Aucun poids taxable → pas de prix de grille');
        $this->assertSame(2500, $chrono13->flatPriceCents);
        $this->assertSame(2500, $chrono13->totalPriceCents());
    }

    public function test_scenario_09_flat_et_standard_cumulent_grille_et_forfait(): void
    {
        $quote = $this->quoteFor(['TX-10' => 1, 'TX-02' => 1]); // flat 12 kg + standard 7 kg

        $chrono13 = $this->service($quote, 'chrono13');
        $this->assertSame(
            2790,
            $chrono13?->gridPriceCents,
            'Seuls les 7 kg de la ligne standard alimentent la grille (tranche 10 kg)',
        );
        $this->assertSame(2500, $chrono13->flatPriceCents);
        $this->assertSame(5290, $chrono13->totalPriceCents());
    }

    public function test_scenario_10_forfait_multiplie_par_la_quantite(): void
    {
        $quote = $this->quoteFor(['TX-10' => 3]);

        $this->assertSame(7500, $this->service($quote, 'chrono13')?->flatPriceCents);
    }

    // ── 11 et 12. Mode free ──────────────────────────────────────────────────

    public function test_scenario_11_panier_100_pourcent_free_propose_livraison_offerte(): void
    {
        $quote = $this->quoteFor(['TX-13' => 1]);

        $this->assertCount(1, $quote->options);
        $this->assertSame(ShippingCalculator::FREE_SHIPPING_IDENTIFIER, $quote->options[0]->identifier);
        $this->assertSame(0, $quote->options[0]->totalPriceCents());
    }

    public function test_scenario_12_ligne_free_exclue_du_poids_taxable(): void
    {
        $quote = $this->quoteFor(['TX-13' => 1, 'TX-01' => 1]); // free 30 kg + standard 1,5 kg

        $this->assertSame(
            1890,
            $this->service($quote, 'chrono13')?->totalPriceCents(),
            'Les 30 kg de la ligne free ne doivent pas alimenter la grille',
        );
    }

    // ── 13 et 14. Mode quote ─────────────────────────────────────────────────

    public function test_scenario_13_panier_100_pourcent_devis_ne_propose_aucun_tarif(): void
    {
        $quote = $this->quoteFor(['TX-14' => 1]);

        $this->assertTrue($quote->isEmpty());
        $this->assertNotEmpty($quote->blockers);
    }

    public function test_scenario_14_panier_mixte_tarife_les_lignes_payables(): void
    {
        $quote = $this->quoteFor(['TX-14' => 1, 'TX-01' => 1]);

        $this->assertSame(
            1890,
            $this->service($quote, 'chrono13')?->totalPriceCents(),
            'Seule la ligne payable (1,5 kg) est tarifée',
        );
        $this->assertNotEmpty($quote->blockers, 'La ligne devis doit bloquer le paiement');
    }

    // ── 15 à 17. Héritage fournisseur ────────────────────────────────────────

    public function test_scenario_15_inherit_port_inclus_donne_livraison_offerte(): void
    {
        $quote = $this->quoteFor(['TX-16' => 1]); // fournisseur port_inclus = oui

        $this->assertCount(1, $quote->options);
        $this->assertSame(ShippingCalculator::FREE_SHIPPING_IDENTIFIER, $quote->options[0]->identifier);
    }

    public function test_scenario_16_inherit_port_facture_suit_la_grille(): void
    {
        $quote = $this->quoteFor(['TX-17' => 1]); // fournisseur SOMFY, port_inclus = non, 6 kg

        $this->assertSame(2790, $this->service($quote, 'chrono13')?->totalPriceCents());
    }

    public function test_scenario_17_inherit_cas_par_cas_suit_la_grille(): void
    {
        $quote = $this->quoteFor(['TX-18' => 1]); // port_inclus = cas_par_cas → standard, 10 kg

        $this->assertSame(2790, $this->service($quote, 'chrono13')?->totalPriceCents());
    }

    // ── 18. Override franco manuel sur un mode flat ──────────────────────────

    public function test_scenario_18_override_franco_sur_flat_annule_la_grille_pas_le_forfait(): void
    {
        $quote = $this->quoteFor(['TX-12' => 1]); // flat 40 € HT, 600 € HT, franco forcé à true

        $chrono13 = $this->service($quote, 'chrono13');
        $this->assertTrue($chrono13?->franco, 'Le franco s\'applique grâce à l\'override manuel');
        $this->assertSame(0, $chrono13->gridPriceCents);
        $this->assertSame(4000, $chrono13->flatPriceCents, 'Le forfait n\'est jamais annulé par le franco');
        $this->assertSame(4000, $chrono13->totalPriceCents());
    }

    // ── 19. Corse ────────────────────────────────────────────────────────────

    public function test_scenario_19_corse_ajoute_le_supplement_apres_le_franco(): void
    {
        $quote = $this->quoteFor(['TX-08' => 1], postcode: '20000');

        $chrono13 = $this->service($quote, 'chrono13');
        $this->assertTrue($chrono13?->franco);
        $this->assertSame(0, $chrono13->gridPriceCents);
        $this->assertSame(800, $chrono13->autoSurchargeCents);
        $this->assertSame(800, $chrono13->totalPriceCents());
    }

    // ── 20. Rupture de stock ─────────────────────────────────────────────────

    public function test_scenario_20_produit_en_rupture_refuse_l_ajout_au_panier(): void
    {
        $variant = ProductVariant::query()->where('sku', 'TX-20')->firstOrFail();

        $this->assertSame(0, $variant->stock);
        $this->assertSame('in_stock', $variant->purchasable);

        $currency = Currency::query()->where('default', true)->firstOrFail();
        $cart = Cart::factory()->create(['currency_id' => $currency->id]);
        CartSession::use($cart);

        $this->expectException(CartException::class);
        $cart->add($variant, 1);
    }
}
