<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Facades\Cache;
use Mockery;
use Pko\ShippingCommon\Carriers\CarrierDefinition;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Dto\QuoteResponse;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingMode;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use RuntimeException;
use Tests\TestCase;

class LivePricingResolverTest extends TestCase
{
    private CarrierRegistry $registry;

    private array $services = [
        ['code' => 'STD', 'label' => 'Standard', 'enabled' => true],
    ];

    private array $grid = [
        ['service_code' => null, 'max_kg' => 5, 'price' => 999],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        // Stub registry
        $this->registry = new CarrierRegistry;
        $this->registry->register(new CarrierDefinition(
            code: 'testcarrier',
            displayName: 'Test',
            icon: 'heroicon-o-truck',
            clientServiceId: 'pko.shipping.carrier.testcarrier',
            secretsModule: 'testcarrier',
            credentialLabels: ['account' => 'Compte'],
            supportsLive: true,
        ));

        // Stub repos (no DB)
        $gridRepo = Mockery::mock(CarrierGridRepository::class);
        $gridRepo->shouldReceive('forCarrier')->andReturn($this->grid);

        $serviceRepo = Mockery::mock(CarrierServiceRepository::class);
        $serviceRepo->shouldReceive('enabledFor')->andReturn($this->services);

        $this->app->instance(CarrierRegistry::class, $this->registry);
        $this->app->instance(CarrierGridRepository::class, $gridRepo);
        $this->app->instance(CarrierServiceRepository::class, $serviceRepo);

        // PricingModeResolver stub (avoid hitting Setting model / pko_storefront_settings)
        $modes = Mockery::mock(PricingModeResolver::class);
        $modes->shouldReceive('getFor')->andReturnUsing(fn () => $this->currentMode);
        $modes->shouldReceive('setFor')->andReturnUsing(function (string $c, PricingMode $m) {
            $this->currentMode = $m;
        });
        $this->app->instance(PricingModeResolver::class, $modes);

        $this->currentMode = PricingMode::GRID;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private PricingMode $currentMode = PricingMode::GRID;

    public function test_grid_mode_bypasses_live_call(): void
    {
        $resolver = $this->app->make(LivePricingResolver::class);

        $quotes = $resolver->resolveLive(
            carrier: 'testcarrier',
            request: new QuoteRequest(2.0, '75001', 'FR'),
            livePricer: fn () => throw new RuntimeException('live should not be called'),
            depZip: '69007',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame(999, $quotes[0]->priceCents);
    }

    public function test_live_with_fallback_uses_grid_when_client_throws(): void
    {
        $this->currentMode = PricingMode::LIVE_WITH_FALLBACK;
        $resolver = $this->app->make(LivePricingResolver::class);

        $quotes = $resolver->resolveLive(
            carrier: 'testcarrier',
            request: new QuoteRequest(2.0, '75001', 'FR'),
            livePricer: fn () => throw new RuntimeException('SOAP down'),
            depZip: '69007',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame(999, $quotes[0]->priceCents);
    }

    public function test_live_only_skips_service_when_client_throws(): void
    {
        $this->currentMode = PricingMode::LIVE_ONLY;
        $resolver = $this->app->make(LivePricingResolver::class);

        $quotes = $resolver->resolveLive(
            carrier: 'testcarrier',
            request: new QuoteRequest(2.0, '75001', 'FR'),
            livePricer: fn () => throw new RuntimeException('SOAP down'),
            depZip: '69007',
        );

        $this->assertSame([], $quotes);
    }

    public function test_live_success_is_cached(): void
    {
        $this->currentMode = PricingMode::LIVE_WITH_FALLBACK;
        $resolver = $this->app->make(LivePricingResolver::class);

        $calls = 0;
        $pricer = function (string $service) use (&$calls): QuoteResponse {
            $calls++;

            return new QuoteResponse(
                serviceCode: $service,
                serviceLabel: 'Standard',
                priceCents: 1234,
            );
        };

        $first = $resolver->resolveLive('testcarrier', new QuoteRequest(2.0, '75001', 'FR'), $pricer, '69007');
        $second = $resolver->resolveLive('testcarrier', new QuoteRequest(2.0, '75001', 'FR'), $pricer, '69007');

        $this->assertSame(1, $calls, 'live pricer should only be called once; 2nd hit is cached');
        $this->assertSame(1234, $first[0]->priceCents);
        $this->assertSame(1234, $second[0]->priceCents);
    }
}
