<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

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
use Pko\ShippingCommon\Pricing\ShippingCalculator;
use Pko\ShippingCommon\Pricing\ShippingQuote;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

/**
 * Tests des bases de calcul franco (eligible_only vs cart_total) et des services configurables.
 *
 * RefreshDatabase est indispensable : Setting::set() persiste en base
 * (updateOrCreate), alors que Setting::forget() ne vide que le cache.
 */
class FrancoModifierBasisTest extends TestCase
{
    use RefreshDatabase;

    private CarrierClient&MockInterface $chronoClient;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();

        TaxClass::create(['name' => 'Default', 'default' => true]);

        $this->chronoClient = Mockery::mock(CarrierClient::class);
        $this->chronoClient->shouldReceive('carrierCode')->andReturn('chronopost')->byDefault();
        $this->app->singleton('pko.shipping.carrier.chronopost', fn () => $this->chronoClient);

        $registry = $this->app->make(CarrierRegistry::class);
        if (! $registry->has('chronopost')) {
            $registry->register(new CarrierDefinition(
                code: 'chronopost', displayName: 'Chronopost', icon: 'heroicon-o-truck',
                clientServiceId: 'pko.shipping.carrier.chronopost', secretsModule: 'chronopost',
                credentialLabels: [],
            ));
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeLine(
        bool $francoEligible = true,
        string $portMode = 'standard',
        int $subtotalHtCents = 30000,
    ): object {
        $product = (object) [
            'pko_franco_eligible' => $francoEligible,
            'pko_port_mode' => $portMode,
            'pko_transport_price_cents' => null,
            'pko_supplier_id' => null,
        ];

        $variant = (object) ['weight_value' => 1.0, 'weight_unit' => 'kg', 'product' => $product];

        return (object) [
            'purchasable' => $variant,
            'quantity' => 1,
            'subTotal' => (object) ['value' => $subtotalHtCents],
        ];
    }

    private function calculate(array $lines, array $quotes = []): ShippingQuote
    {
        $responses = array_map(
            fn (array $q) => new QuoteResponse($q[0], $q[1], $q[2]),
            $quotes,
        );

        $this->chronoClient->shouldReceive('quote')->andReturn($responses);

        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $country = (object) ['iso2' => 'FR'];
        $address = (object) ['postcode' => '75001', 'country' => $country];

        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);
        $cart->shouldReceive('getAttribute')->with('shippingAddress')->andReturn($address);
        $cart->shouldReceive('offsetExists')->andReturnUsing(
            fn ($key): bool => in_array($key, ['lines', 'currency', 'shippingAddress'], true),
        );

        return app(ShippingCalculator::class)->calculate($cart);
    }

    // ── Tests basis = eligible_only (défaut) ──────────────────────────────────

    public function test_eligible_only_applique_franco_si_panier_homogene_eligible(): void
    {
        $quote = $this->calculate(
            lines: [$this->makeLine(francoEligible: true, subtotalHtCents: 60000)],
            quotes: [['chrono13', 'Chrono 13', 990]],
        );

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertTrue($opt->franco, 'Franco doit s\'appliquer sur panier homogène éligible');
        $this->assertSame(0, $opt->totalPriceCents());
    }

    public function test_eligible_only_bloque_si_ligne_exclue(): void
    {
        $quote = $this->calculate(
            lines: [
                $this->makeLine(francoEligible: true, subtotalHtCents: 55000),
                $this->makeLine(francoEligible: false, subtotalHtCents: 10000),
            ],
            quotes: [['chrono13', 'Chrono 13', 990]],
        );

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertFalse($opt->franco, 'Franco bloqué par ligne exclue en mode eligible_only');
        $this->assertSame(990, $opt->totalPriceCents());
    }

    // ── Tests basis = cart_total ──────────────────────────────────────────────

    public function test_cart_total_applique_franco_meme_avec_ligne_exclue(): void
    {
        Setting::set('shipping.franco.basis', 'cart_total');

        $quote = $this->calculate(
            lines: [
                $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
                $this->makeLine(francoEligible: false, subtotalHtCents: 15000), // total 55 000 >= 50 000
            ],
            quotes: [['chrono13', 'Chrono 13', 990]],
        );

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertTrue($opt->franco, 'Franco cart_total doit s\'appliquer même avec ligne exclue');
        $this->assertSame(0, $opt->totalPriceCents());
    }

    public function test_cart_total_ne_depasse_pas_seuil(): void
    {
        Setting::set('shipping.franco.basis', 'cart_total');
        Setting::set('shipping.franco.threshold_cents', 50000);

        $quote = $this->calculate(
            lines: [
                $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
                $this->makeLine(francoEligible: false, subtotalHtCents: 15000), // total 35 000 < 50 000
            ],
            quotes: [['chrono13', 'Chrono 13', 990]],
        );

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertFalse($opt->franco, 'Pas de franco si total cart_total < seuil');
        $this->assertSame(990, $opt->totalPriceCents());
    }

    // ── Tests services configurables ─────────────────────────────────────────

    public function test_franco_multi_services_rend_tous_gratuits(): void
    {
        Setting::set('shipping.franco.services', ['chrono13', 'chrono10']);
        Setting::set('shipping.franco.threshold_cents', 50000);

        $quote = $this->calculate(
            lines: [$this->makeLine(francoEligible: true, subtotalHtCents: 60000)],
            quotes: [
                ['chrono13', 'Chrono 13', 990],
                ['chrono10', 'Chrono 10', 1490],
                ['chrono_relais', 'Chrono Relais', 690],
            ],
        );

        $this->assertTrue(collect($quote->options)->firstWhere('serviceCode', 'chrono13')->franco);
        $this->assertTrue(collect($quote->options)->firstWhere('serviceCode', 'chrono10')->franco);
        $this->assertFalse(collect($quote->options)->firstWhere('serviceCode', 'chrono_relais')->franco);
    }

    public function test_service_inconnu_ignore_sans_planter(): void
    {
        Setting::set('shipping.franco.services', ['service_inexistant']);

        $quote = $this->calculate(
            lines: [$this->makeLine(francoEligible: true, subtotalHtCents: 60000)],
            quotes: [['chrono13', 'Chrono 13', 990]],
        );

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertNotNull($opt);
        $this->assertFalse($opt->franco, 'Service non ciblé ne doit pas être franco');
        $this->assertSame(990, $opt->totalPriceCents());
    }

    // ── Test seuil configurable ───────────────────────────────────────────────

    public function test_seuil_db_gagne_sur_config(): void
    {
        Setting::set('shipping.franco.threshold_cents', 30000);

        // 35 000 >= 30 000 → franco doit s'appliquer (n'atteindrait pas 50 000 par défaut)
        $quote = $this->calculate(
            lines: [$this->makeLine(francoEligible: true, subtotalHtCents: 35000)],
            quotes: [['chrono13', 'Chrono 13', 990]],
        );

        $opt = collect($quote->options)->firstWhere('serviceCode', 'chrono13');
        $this->assertTrue($opt->franco, 'Franco doit s\'appliquer avec seuil DB = 30 000 cents');
    }
}
