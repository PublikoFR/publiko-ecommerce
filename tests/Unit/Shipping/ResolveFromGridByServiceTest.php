<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Illuminate\Support\Facades\Cache;
use Mockery;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingMode;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Tests\TestCase;

/**
 * Validates that resolveFromGrid picks a distinct price per service and
 * masks services that have no bracket covering the requested weight.
 */
class ResolveFromGridByServiceTest extends TestCase
{
    /** Chronopost 3-service setup (matching the L1 data migration). */
    private array $chronopostServices = [
        ['code' => 'chrono_relais', 'label' => 'Livraison économique — Chrono Relais', 'enabled' => true],
        ['code' => 'chrono13',      'label' => 'Livraison standard — Chrono 13',       'enabled' => true],
        ['code' => 'chrono10',      'label' => 'Livraison express — Chrono 10',         'enabled' => true],
    ];

    /** Brackets as CarrierGridRepository::forCarrier returns them (sorted null first, then service asc, then max_kg asc). */
    private array $gridByService = [
        'chrono_relais' => [
            ['service_code' => 'chrono_relais', 'max_kg' => 2,  'price' => 1490],
            ['service_code' => 'chrono_relais', 'max_kg' => 5,  'price' => 1790],
            ['service_code' => 'chrono_relais', 'max_kg' => 10, 'price' => 2290],
            ['service_code' => 'chrono_relais', 'max_kg' => 20, 'price' => 3290],
            // no 30 kg bracket → service masked above 20 kg
        ],
        'chrono13' => [
            ['service_code' => 'chrono13', 'max_kg' => 2,  'price' => 1890],
            ['service_code' => 'chrono13', 'max_kg' => 5,  'price' => 2290],
            ['service_code' => 'chrono13', 'max_kg' => 10, 'price' => 2790],
            ['service_code' => 'chrono13', 'max_kg' => 20, 'price' => 3990],
            ['service_code' => 'chrono13', 'max_kg' => 30, 'price' => 5490],
        ],
        'chrono10' => [
            ['service_code' => 'chrono10', 'max_kg' => 2,  'price' => 2490],
            ['service_code' => 'chrono10', 'max_kg' => 5,  'price' => 2890],
            ['service_code' => 'chrono10', 'max_kg' => 10, 'price' => 3490],
            ['service_code' => 'chrono10', 'max_kg' => 20, 'price' => 4990],
            ['service_code' => 'chrono10', 'max_kg' => 30, 'price' => 6990],
        ],
    ];

    /** Colissimo-style grid: all brackets have service_code = null (shared across services). */
    private array $colissimoServices = [
        ['code' => 'DOM', 'label' => 'Colissimo Domicile',            'enabled' => true],
        ['code' => 'DOS', 'label' => 'Colissimo Domicile Signature',  'enabled' => true],
    ];

    private array $colissimoNullGrid = [
        ['service_code' => null, 'max_kg' => 2,  'price' => 690],
        ['service_code' => null, 'max_kg' => 5,  'price' => 990],
        ['service_code' => null, 'max_kg' => 30, 'price' => 2490],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeResolver(array $services, array $gridBySvcCode): LivePricingResolver
    {
        $gridRepo = Mockery::mock(CarrierGridRepository::class);
        foreach ($services as $svc) {
            $gridRepo->shouldReceive('forCarrier')
                ->with('testcarrier', $svc['code'])
                ->andReturn($gridBySvcCode[$svc['code']] ?? []);
        }

        $serviceRepo = Mockery::mock(CarrierServiceRepository::class);
        $serviceRepo->shouldReceive('enabledFor')->andReturn($services);

        $modes = Mockery::mock(PricingModeResolver::class);
        $modes->shouldReceive('getFor')->andReturn(PricingMode::GRID);

        $this->app->instance(CarrierGridRepository::class, $gridRepo);
        $this->app->instance(CarrierServiceRepository::class, $serviceRepo);
        $this->app->instance(PricingModeResolver::class, $modes);

        return $this->app->make(LivePricingResolver::class);
    }

    public function test_three_distinct_prices_at_1_8_kg(): void
    {
        $resolver = $this->makeResolver($this->chronopostServices, $this->gridByService);
        $quotes = $resolver->resolveFromGrid('testcarrier', new QuoteRequest(1.8, '75001', 'FR'));

        $this->assertCount(3, $quotes);
        $indexed = array_column($quotes, 'priceCents', 'serviceCode');

        $this->assertSame(1490, $indexed['chrono_relais']);
        $this->assertSame(1890, $indexed['chrono13']);
        $this->assertSame(2490, $indexed['chrono10']);
    }

    public function test_chrono_relais_masked_above_20_kg(): void
    {
        $resolver = $this->makeResolver($this->chronopostServices, $this->gridByService);
        $quotes = $resolver->resolveFromGrid('testcarrier', new QuoteRequest(25.0, '75001', 'FR'));

        $this->assertCount(2, $quotes, 'chrono_relais should be masked above 20 kg');
        $codes = array_column($quotes, 'serviceCode');
        $this->assertNotContains('chrono_relais', $codes);
        $this->assertContains('chrono13', $codes);
        $this->assertContains('chrono10', $codes);

        $indexed = array_column($quotes, 'priceCents', 'serviceCode');
        $this->assertSame(5490, $indexed['chrono13']);
        $this->assertSame(6990, $indexed['chrono10']);
    }

    public function test_null_grid_retro_compat_applies_same_price_to_all_services(): void
    {
        $nullGridByService = [
            'DOM' => $this->colissimoNullGrid,
            'DOS' => $this->colissimoNullGrid,
        ];

        $resolver = $this->makeResolver($this->colissimoServices, $nullGridByService);
        $quotes = $resolver->resolveFromGrid('testcarrier', new QuoteRequest(1.8, '75001', 'FR'));

        $this->assertCount(2, $quotes);
        foreach ($quotes as $quote) {
            $this->assertSame(690, $quote->priceCents, 'All null-grid services share the same price');
        }
    }

    public function test_service_masked_when_weight_exceeds_all_brackets(): void
    {
        $resolver = $this->makeResolver($this->chronopostServices, $this->gridByService);
        $quotes = $resolver->resolveFromGrid('testcarrier', new QuoteRequest(35.0, '75001', 'FR'));

        $this->assertCount(0, $quotes, 'All services are masked when weight exceeds every bracket');
    }

    public function test_exact_bracket_boundary_is_included(): void
    {
        $resolver = $this->makeResolver($this->chronopostServices, $this->gridByService);
        // Exactly 20 kg → chrono_relais bracket 20 should match
        $quotes = $resolver->resolveFromGrid('testcarrier', new QuoteRequest(20.0, '75001', 'FR'));

        $indexed = array_column($quotes, 'priceCents', 'serviceCode');
        $this->assertArrayHasKey('chrono_relais', $indexed);
        $this->assertSame(3290, $indexed['chrono_relais']);
    }
}
