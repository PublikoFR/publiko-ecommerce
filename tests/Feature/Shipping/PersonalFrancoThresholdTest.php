<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Carriers\CarrierDefinition;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Contracts\CustomerFrancoThresholdProvider;
use Pko\ShippingCommon\Dto\QuoteResponse;
use Pko\ShippingCommon\Pricing\ShippingCalculator;
use Pko\ShippingCommon\Pricing\ShippingQuote;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

/**
 * Seuil de franco personnel (avantage fidélité « franco abaissé ») :
 * seuil effectif = min(global, personnel), forfaits flat toujours facturés.
 */
class PersonalFrancoThresholdTest extends TestCase
{
    use RefreshDatabase;

    private CarrierClient&MockInterface $chronoClient;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Setting::set('shipping.franco.threshold_cents', 50000);

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

    private function personalThreshold(?int $cents): void
    {
        $this->app->instance(CustomerFrancoThresholdProvider::class, new class($cents) implements CustomerFrancoThresholdProvider
        {
            public function __construct(private readonly ?int $cents) {}

            public function thresholdCentsFor(CartContract $cart): ?int
            {
                return $this->cents;
            }
        });
    }

    private function makeLine(int $subtotalHtCents, string $portMode = 'standard', ?int $transportCents = null, bool $francoEligible = true): object
    {
        $product = (object) [
            'pko_franco_eligible' => $francoEligible,
            'pko_port_mode' => $portMode,
            'pko_transport_price_cents' => $transportCents,
            'pko_supplier_id' => null,
        ];

        return (object) [
            'purchasable' => (object) ['weight_value' => 1.0, 'weight_unit' => 'kg', 'product' => $product],
            'quantity' => 1,
            'subTotal' => (object) ['value' => $subtotalHtCents],
        ];
    }

    private function calculate(array $lines): ShippingQuote
    {
        $this->chronoClient->shouldReceive('quote')->andReturn([
            new QuoteResponse('chrono13', 'Chrono 13', 990),
            new QuoteResponse('chrono10', 'Chrono 10', 1490),
        ]);

        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $address = (object) ['postcode' => '75001', 'country' => (object) ['iso2' => 'FR']];

        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);
        $cart->shouldReceive('getAttribute')->with('shippingAddress')->andReturn($address);
        $cart->shouldReceive('offsetExists')->andReturnUsing(
            fn ($key): bool => in_array($key, ['lines', 'currency', 'shippingAddress'], true),
        );

        return app(ShippingCalculator::class)->calculate($cart);
    }

    private function option(ShippingQuote $quote, string $service): object
    {
        return collect($quote->options)->firstWhere('serviceCode', $service);
    }

    public function test_seuil_personnel_plus_bas_declenche_le_franco(): void
    {
        $this->personalThreshold(25000);

        // 30 000 ¢ : sous le global (50 000), au-dessus du personnel (25 000).
        $quote = $this->calculate([$this->makeLine(30000)]);

        $this->assertTrue($this->option($quote, 'chrono13')->franco);
        $this->assertSame(0, $this->option($quote, 'chrono13')->totalPriceCents());
        $this->assertFalse($this->option($quote, 'chrono10')->franco, 'Chrono 10 jamais offert, même en franco personnel');
        $this->assertContains(
            ['type' => 'franco_reached', 'threshold_cents' => 25000, 'personal' => true],
            $quote->banners,
        );
    }

    public function test_sans_seuil_personnel_le_global_s_applique(): void
    {
        $this->personalThreshold(null);

        $quote = $this->calculate([$this->makeLine(30000)]);

        $this->assertFalse($this->option($quote, 'chrono13')->franco);
        $this->assertSame(990, $this->option($quote, 'chrono13')->totalPriceCents());
        $this->assertContains(
            ['type' => 'franco_progress', 'remaining_cents' => 20000, 'threshold_cents' => 50000, 'personal' => false],
            $quote->banners,
        );
    }

    public function test_progression_calculee_sur_le_seuil_personnel(): void
    {
        $this->personalThreshold(25000);

        $quote = $this->calculate([$this->makeLine(20000)]);

        $this->assertFalse($this->option($quote, 'chrono13')->franco);
        $this->assertContains(
            ['type' => 'franco_progress', 'remaining_cents' => 5000, 'threshold_cents' => 25000, 'personal' => true],
            $quote->banners,
        );
    }

    public function test_seuil_personnel_superieur_au_global_ne_penalise_pas(): void
    {
        $this->personalThreshold(80000);

        $quote = $this->calculate([$this->makeLine(60000)]);

        $this->assertTrue($this->option($quote, 'chrono13')->franco);
        $this->assertSame(50000, ShippingSettings::thresholdCentsForCart(new Cart));
    }

    public function test_forfait_transport_specifique_reste_facture_en_franco_personnel(): void
    {
        Setting::set('shipping.franco.basis', 'cart_total');
        $this->personalThreshold(25000);

        // 20 000 standard + 10 000 flat = 30 000 ≥ 25 000 : grille offerte, forfait 1 500 ¢ dû.
        $quote = $this->calculate([
            $this->makeLine(20000),
            $this->makeLine(10000, portMode: 'flat', transportCents: 1500, francoEligible: false),
        ]);

        $opt = $this->option($quote, 'chrono13');
        $this->assertTrue($opt->franco);
        $this->assertSame(0, $opt->gridPriceCents);
        $this->assertSame(1500, $opt->flatPriceCents);
        $this->assertSame(1500, $opt->totalPriceCents());
    }
}
