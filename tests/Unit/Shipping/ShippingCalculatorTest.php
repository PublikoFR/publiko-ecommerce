<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Carriers\CarrierDefinition;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\QuoteResponse;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Pricing\ShippingCalculator;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

/**
 * Tests unitaires de ShippingCalculator.
 *
 * Tests non exécutés en CI lors du commit L4 (worktree non monté dans Docker).
 * Couverture : franco (eligible_only / cart_total), surcharges (auto / quote),
 * cas flat-only, cas tout-gratuit, zone, banners, blockers.
 */
class ShippingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private CarrierRegistry $registry;

    private CarrierClient&MockInterface $chronoClient;

    private ShippingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::forget();

        $this->chronoClient = Mockery::mock(CarrierClient::class);
        $this->chronoClient->shouldReceive('carrierCode')->andReturn('chronopost')->byDefault();

        $this->app->singleton('pko.shipping.carrier.chronopost', fn () => $this->chronoClient);

        $this->registry = $this->app->make(CarrierRegistry::class);
        if (! $this->registry->has('chronopost')) {
            $this->registry->register(new CarrierDefinition(
                code: 'chronopost',
                displayName: 'Chronopost',
                icon: 'heroicon-o-truck',
                clientServiceId: 'pko.shipping.carrier.chronopost',
                secretsModule: 'chronopost',
                credentialLabels: [],
            ));
        }

        $this->calculator = $this->app->make(ShippingCalculator::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeLine(
        bool $francoEligible = true,
        string $portMode = 'standard',
        int $subtotalHtCents = 30000,
        float $weightKg = 1.0,
        ?int $transportPriceCents = null,
        ?int $supplierId = null,
    ): object {
        $product = (object) [
            'pko_franco_eligible' => $francoEligible,
            'pko_port_mode' => $portMode,
            'pko_transport_price_cents' => $transportPriceCents,
            'pko_supplier_id' => $supplierId,
        ];

        $variant = (object) [
            'weight_value' => $weightKg,
            'weight_unit' => 'kg',
            'product' => $product,
        ];

        return (object) [
            'purchasable' => $variant,
            'quantity' => 1,
            'subTotal' => (object) ['value' => $subtotalHtCents],
        ];
    }

    private function makeCart(
        array $lines,
        string $postcode = '75001',
        string $countryIso = 'FR',
    ): Cart&MockInterface {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $country = (object) ['iso2' => $countryIso];
        $address = (object) ['postcode' => $postcode, 'country' => $country];

        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);
        $cart->shouldReceive('getAttribute')->with('shippingAddress')->andReturn($address);
        $cart->shouldReceive('offsetExists')->andReturnUsing(
            fn ($key): bool => in_array($key, ['lines', 'currency', 'shippingAddress'], true),
        );

        return $cart;
    }

    private function mockQuotes(int ...$priceCents): void
    {
        $responses = [];
        $serviceCodes = ['chrono13', 'chrono_relais', 'chrono10'];

        foreach ($priceCents as $i => $price) {
            $code = $serviceCodes[$i] ?? 'service'.$i;
            $responses[] = new QuoteResponse(
                serviceCode: $code,
                serviceLabel: ucfirst($code),
                priceCents: $price,
            );
        }

        $this->chronoClient
            ->shouldReceive('quote')
            ->once()
            ->andReturn($responses);
    }

    // ── Tests : adresse / zone ─────────────────────────────────────────────────

    public function test_sans_adresse_retourne_quote_vide(): void
    {
        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('shippingAddress')->andReturn(null);

        $quote = $this->calculator->calculate($cart);

        $this->assertTrue($quote->isEmpty());
        $this->assertSame([], $quote->blockers);
    }

    public function test_hors_metropole_sans_surcharge_corse_retourne_vide(): void
    {
        $cart = $this->makeCart(
            lines: [$this->makeLine()],
            postcode: '20200', // Corse
        );

        // Aucun supplément corse en DB → Corse fermée
        $quote = $this->calculator->calculate($cart);

        $this->assertTrue($quote->isEmpty());
    }

    public function test_metropole_retourne_options_carrier(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        $this->mockQuotes(1890, 1490);

        $cart = $this->makeCart(
            lines: [$this->makeLine(subtotalHtCents: 10000)],
            postcode: '75001',
        );

        $quote = $this->calculator->calculate($cart);

        $this->assertFalse($quote->isEmpty());
        $this->assertCount(2, $quote->options);
    }

    // ── Tests : franco eligible_only (défaut) ─────────────────────────────────

    public function test_franco_applique_sur_chrono13_quand_seuil_atteint(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        $this->chronoClient
            ->shouldReceive('quote')
            ->andReturn([
                new QuoteResponse('chrono13', 'Chrono 13', 990),
                new QuoteResponse('chrono_relais', 'Chrono Relais', 690),
            ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $chrono13 = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertNotNull($chrono13);
        $this->assertTrue($chrono13->franco, 'chrono13 doit être franco');
        $this->assertSame(0, $chrono13->gridPriceCents, 'gridPrice doit être 0 quand franco');
        $this->assertSame(0, $chrono13->totalPriceCents());

        $relais = collect($quote->options)->firstWhere('serviceCode', 'chrono_relais');
        $this->assertNotNull($relais);
        $this->assertFalse($relais->franco, 'chrono_relais ne doit pas être franco');
        $this->assertSame(690, $relais->totalPriceCents());
    }

    public function test_franco_non_applique_sous_le_seuil(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000), // < 50 000
        ]);

        $quote = $this->calculator->calculate($cart);

        $chrono13 = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertNotNull($chrono13);
        $this->assertFalse($chrono13->franco);
        $this->assertSame(990, $chrono13->totalPriceCents());
    }

    public function test_franco_bloque_par_ligne_exclue(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 5000), // exclue
        ]);

        $quote = $this->calculator->calculate($cart);

        $chrono13 = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertNotNull($chrono13);
        $this->assertFalse($chrono13->franco, 'Franco doit être bloqué par ligne exclue');
        $this->assertSame(990, $chrono13->totalPriceCents());
    }

    public function test_franco_bloque_par_ligne_mode_quote(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, portMode: 'standard', subtotalHtCents: 60000),
            $this->makeLine(francoEligible: true, portMode: 'quote', subtotalHtCents: 15000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $chrono13 = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertFalse($chrono13->franco, 'Mode quote doit bloquer le franco eligible_only');
    }

    // ── Tests : franco cart_total ──────────────────────────────────────────────

    public function test_franco_cart_total_applique_meme_avec_ligne_exclue(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        Setting::set('shipping.franco.basis', 'cart_total');

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 15000), // 55 000 total > 50 000
        ]);

        $quote = $this->calculator->calculate($cart);

        $chrono13 = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertTrue($chrono13->franco, 'Franco cart_total doit s\'appliquer même avec ligne exclue');
    }

    public function test_franco_cart_total_bloque_sous_seuil(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        Setting::set('shipping.franco.basis', 'cart_total');

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 10000), // 30 000 < 50 000
        ]);

        $quote = $this->calculator->calculate($cart);

        $chrono13 = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertFalse($chrono13->franco);
    }

    // ── Tests : services franco configurables ─────────────────────────────────

    public function test_franco_multi_services_selon_config(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        Setting::set('shipping.franco.services', ['chrono13', 'chrono10']);
        Setting::set('shipping.franco.threshold_cents', 50000);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
            new QuoteResponse('chrono10', 'Chrono 10', 1490),
            new QuoteResponse('chrono_relais', 'Chrono Relais', 690),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $this->assertTrue(collect($quote->options)->firstWhere('serviceCode', 'chrono13')->franco);
        $this->assertTrue(collect($quote->options)->firstWhere('serviceCode', 'chrono10')->franco);
        $this->assertFalse(collect($quote->options)->firstWhere('serviceCode', 'chrono_relais')->franco);
    }

    public function test_seuil_configurable_via_db(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        Setting::set('shipping.franco.threshold_cents', 30000);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        // 35 000 >= 30 000 → franco, mais n'atteindrait pas 50 000 (défaut)
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 35000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $chrono13 = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertTrue($chrono13->franco, 'Seuil DB 30 000 doit prendre le dessus sur le défaut 50 000');
    }

    // ── Tests : surcharges ────────────────────────────────────────────────────

    public function test_supplement_auto_majore_le_prix_carrier(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        ShippingSurcharge::create([
            'code' => 'corse',
            'label' => 'Supplément Corse',
            'amount_cents' => 800,
            'mode' => 'auto',
            'rule' => ['type' => 'corse'],
            'enabled' => true,
        ]);

        // Activer la Corse dans le calculator (il y a un surcharge corse actif)
        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 1890),
        ]);

        $cart = $this->makeCart(
            lines: [$this->makeLine(francoEligible: true, subtotalHtCents: 10000)],
            postcode: '20200',
        );

        $quote = $this->calculator->calculate($cart);

        $opt = collect($quote->options)->first();
        $this->assertNotNull($opt, 'Une option carrier doit être retournée pour la Corse avec surcharge active');
        $this->assertSame(800, $opt->autoSurchargeCents, 'Surcharge corse = 800 cents');
        $this->assertSame(1890 + 800, $opt->totalPriceCents());
    }

    public function test_supplement_auto_pas_applique_en_metropole(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        ShippingSurcharge::create([
            'code' => 'corse',
            'label' => 'Supplément Corse',
            'amount_cents' => 800,
            'mode' => 'auto',
            'rule' => ['type' => 'corse'],
            'enabled' => true,
        ]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 1890),
        ]);

        $cart = $this->makeCart(
            lines: [$this->makeLine(subtotalHtCents: 10000)],
            postcode: '75001',
        );

        $quote = $this->calculator->calculate($cart);

        $opt = collect($quote->options)->first();
        $this->assertNotNull($opt);
        $this->assertSame(0, $opt->autoSurchargeCents, 'Aucun supplément pour la métropole');
        $this->assertSame(1890, $opt->totalPriceCents());
    }

    public function test_supplement_quote_injecte_option_sentinel(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        ShippingSurcharge::create([
            'code' => 'transport_specifique',
            'label' => 'Transport sur devis',
            'amount_cents' => 0,
            'mode' => 'quote',
            'rule' => ['type' => 'corse'],
            'enabled' => true,
        ]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 1890),
        ]);

        $cart = $this->makeCart(
            lines: [$this->makeLine(subtotalHtCents: 10000)],
            postcode: '20200',
        );

        $quote = $this->calculator->calculate($cart);

        $sentinel = collect($quote->options)->first(fn ($o) => $o->identifier === 'surcharge.transport_specifique');
        $this->assertNotNull($sentinel, 'Option sentinel doit être injectée');
        $this->assertTrue($sentinel->isSentinel);
        $this->assertSame(0, $sentinel->totalPriceCents());
    }

    public function test_supplement_auto_ne_majore_pas_les_sentinels(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        // Un auto ET un quote surcharge (tous les deux pour Corse)
        ShippingSurcharge::create([
            'code' => 'corse', 'label' => 'Supplément Corse', 'amount_cents' => 800,
            'mode' => 'auto', 'rule' => ['type' => 'corse'], 'enabled' => true,
        ]);
        ShippingSurcharge::create([
            'code' => 'transport_specifique', 'label' => 'Transport sur devis', 'amount_cents' => 0,
            'mode' => 'quote', 'rule' => ['type' => 'corse'], 'enabled' => true,
        ]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 1890),
        ]);

        $cart = $this->makeCart(
            lines: [$this->makeLine(subtotalHtCents: 10000)],
            postcode: '20200',
        );

        $quote = $this->calculator->calculate($cart);

        $sentinel = collect($quote->options)->first(fn ($o) => $o->isSentinel);
        $this->assertNotNull($sentinel);
        $this->assertSame(0, $sentinel->autoSurchargeCents, 'La surcharge auto ne doit pas majorer un sentinel');
        $this->assertSame(0, $sentinel->totalPriceCents());
    }

    // ── Tests : cas tout-gratuit ──────────────────────────────────────────────

    public function test_toutes_lignes_free_retourne_option_livraison_offerte(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        // Pas d'appel client carrier attendu
        $this->chronoClient->shouldNotReceive('quote');

        $cart = $this->makeCart([
            $this->makeLine(portMode: 'free', subtotalHtCents: 10000),
            $this->makeLine(portMode: 'free', subtotalHtCents: 5000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $this->assertCount(1, $quote->options);
        $opt = $quote->options[0];
        $this->assertSame(ShippingCalculator::FREE_SHIPPING_IDENTIFIER, $opt->identifier);
        $this->assertSame(0, $opt->totalPriceCents());
        $this->assertFalse($opt->isSentinel);
    }

    // ── Tests : cas flat-only ─────────────────────────────────────────────────

    public function test_ligne_flat_seule_enumere_services_avec_forfait(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        // Pas d'appel client (poids=0)
        $this->chronoClient->shouldNotReceive('quote');

        // Configurer des services activés dans la repo
        $serviceRepo = Mockery::mock(CarrierServiceRepository::class);
        $serviceRepo->shouldReceive('enabledFor')
            ->with('chronopost')
            ->andReturn([
                ['code' => 'chrono13', 'label' => 'Chrono 13', 'enabled' => true],
                ['code' => 'chrono_relais', 'label' => 'Chrono Relais', 'enabled' => true],
            ]);
        // Les autres carriers enregistrés (colissimo…) ne doivent pas ajouter d'options.
        $serviceRepo->shouldReceive('enabledFor')
            ->andReturn([]);
        $this->app->instance(CarrierServiceRepository::class, $serviceRepo);
        // ShippingCalculator est un singleton créé dans setUp() avec le vrai repo.
        // On le détruit pour que le prochain make() injecte le mock.
        $this->app->forgetInstance(ShippingCalculator::class);

        $calculator = $this->app->make(ShippingCalculator::class);

        $cart = $this->makeCart([
            $this->makeLine(
                portMode: 'flat',
                subtotalHtCents: 10000,
                weightKg: 0.0,
                transportPriceCents: 500,
            ),
        ]);

        $quote = $calculator->calculate($cart);

        $this->assertCount(2, $quote->options, '2 services activés → 2 options');

        foreach ($quote->options as $opt) {
            $this->assertSame(0, $opt->gridPriceCents, 'gridPrice = 0 (pas de grille pour flat-only)');
            $this->assertSame(500, $opt->flatPriceCents, 'flatPriceCents = 500 (forfait × quantité 1)');
            $this->assertSame(500, $opt->totalPriceCents());
        }
    }

    public function test_forfait_flat_multiplie_par_quantite(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 1890),
        ]);

        // Ligne standard + ligne flat avec quantity=2 et price=300 → flat = 600
        $lineStandard = $this->makeLine(portMode: 'standard', subtotalHtCents: 20000, weightKg: 2.0);
        $lineFlat = (object) [
            'purchasable' => (object) [
                'weight_value' => 0.0,
                'weight_unit' => 'kg',
                'product' => (object) [
                    'pko_franco_eligible' => false,
                    'pko_port_mode' => 'flat',
                    'pko_transport_price_cents' => 300,
                    'pko_supplier_id' => null,
                ],
            ],
            'quantity' => 2,
            'subTotal' => (object) ['value' => 15000],
        ];

        $cart = $this->makeCart([$lineStandard, $lineFlat]);

        $quote = $this->calculator->calculate($cart);

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertNotNull($opt);
        $this->assertSame(600, $opt->flatPriceCents, 'Forfait = 300 × 2 = 600 cents');
        $this->assertSame(1890 + 600, $opt->totalPriceCents());
    }

    // ── Tests : blockers ──────────────────────────────────────────────────────

    public function test_ligne_quote_produit_blocker(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(portMode: 'standard', subtotalHtCents: 30000),
            $this->makeLine(portMode: 'quote', subtotalHtCents: 10000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $this->assertNotEmpty($quote->blockers, 'Une ligne quote doit produire un blocker');
        $this->assertStringContainsString('devis', $quote->blockers[0]);
    }

    public function test_aucun_blocker_sans_ligne_quote(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(portMode: 'standard', subtotalHtCents: 30000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $this->assertSame([], $quote->blockers);
    }

    // ── Tests : banners ──────────────────────────────────────────────────────

    public function test_banner_franco_reached_quand_franco_applique(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $types = array_column($quote->banners, 'type');
        $this->assertContains('franco_reached', $types);
        $this->assertNotContains('franco_progress', $types);
    }

    public function test_banner_franco_progress_quand_sous_seuil(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        Setting::set('shipping.franco.threshold_cents', 50000);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $progress = collect($quote->banners)->firstWhere('type', 'franco_progress');
        $this->assertNotNull($progress);
        $this->assertSame(30000, $progress['remaining_cents'], 'remaining = 50 000 − 20 000 = 30 000');
    }

    public function test_banner_excluded_lines_quand_ligne_non_eligible(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 30000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 5000),
        ]);

        $quote = $this->calculator->calculate($cart);

        $types = array_column($quote->banners, 'type');
        $this->assertContains('excluded_lines', $types);
    }

    public function test_banner_multi_colis_quand_stock_weklo_et_fournisseur(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
        ]);

        $cart = $this->makeCart([
            $this->makeLine(supplierId: null, subtotalHtCents: 20000),   // stock Weklo
            $this->makeLine(supplierId: 1, subtotalHtCents: 10000),      // fournisseur
        ]);

        $quote = $this->calculator->calculate($cart);

        $types = array_column($quote->banners, 'type');
        $this->assertContains('multi_colis', $types);
    }

    // ── Tests : corse + franco = supplément seulement ─────────────────────────

    public function test_corse_franco_atteint_paie_seulement_supplement(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        ShippingSurcharge::create([
            'code' => 'corse', 'label' => 'Supplément Corse', 'amount_cents' => 800,
            'mode' => 'auto', 'rule' => ['type' => 'corse'], 'enabled' => true,
        ]);

        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 1890),
        ]);

        $cart = $this->makeCart(
            lines: [$this->makeLine(francoEligible: true, subtotalHtCents: 60000)],
            postcode: '20200', // Corse
        );

        $quote = $this->calculator->calculate($cart);

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertNotNull($opt);
        $this->assertTrue($opt->franco, 'Franco doit s\'appliquer sur chrono13');
        $this->assertSame(0, $opt->gridPriceCents, 'Grid = 0 (franco)');
        $this->assertSame(800, $opt->autoSurchargeCents, 'Supplément Corse = 800');
        $this->assertSame(800, $opt->totalPriceCents(), 'Total = 0 (franco) + 800 (corse)');
    }

    // ── Tests : poids hors grille → sentinelle "sur devis" ───────────────────

    public function test_poids_hors_grille_produit_une_sentinelle_sur_devis(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        // Aucun bracket ne couvre le poids → le client carrier ne renvoie aucun tarif.
        $this->chronoClient->shouldReceive('quote')->andReturn([]);

        $cart = $this->makeCart([
            $this->makeLine(subtotalHtCents: 40000, weightKg: 35.0),
        ]);

        $quote = $this->calculator->calculate($cart);

        $this->assertCount(1, $quote->options);

        $option = $quote->options[0];
        $this->assertSame(ShippingCalculator::OVERWEIGHT_QUOTE_IDENTIFIER, $option->identifier);
        $this->assertTrue($option->isSentinel);
        $this->assertSame(0, $option->totalPriceCents());
        $this->assertTrue($quote->isQuoteOnly());
        $this->assertNotEmpty($quote->blockers, 'Le paiement doit être bloqué hors grille');
    }

    public function test_poids_dans_la_grille_ne_produit_pas_de_sentinelle(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);
        $this->mockQuotes(1890, 1490);

        $cart = $this->makeCart([
            $this->makeLine(subtotalHtCents: 10000, weightKg: 5.0),
        ]);

        $quote = $this->calculator->calculate($cart);

        $this->assertFalse($quote->isQuoteOnly());
        $this->assertSame([], $quote->blockers);
    }

    // ── Test : identifier default option ─────────────────────────────────────

    public function test_default_option_identifier_est_chrono13(): void
    {
        $this->assertSame('chronopost.chrono13', ShippingCalculator::DEFAULT_OPTION_IDENTIFIER);
    }
}
