<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Facades\Cache;
use Mockery;
use Pko\ShippingChronopost\Services\ChronopostClient;
use Pko\ShippingChronopost\Services\QuickCostResponse;
use Pko\ShippingChronopost\Services\QuickCostSoapClient;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingMode;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Pko\ShippingCommon\Support\CarrierProductCodeResolver;
use Tests\TestCase;

/**
 * Mode live Chronopost : le montant injecté dans le moteur doit être de même
 * nature que les grilles (HT par défaut), sinon ShippingCalculator ajoute la
 * TVA une seconde fois. Aucun appel réseau : QuickCostSoapClient est mocké.
 */
class ChronopostLiveQuoteTest extends TestCase
{
    private const SETTINGS_CACHE_KEY = 'pko.storefront.settings.v1';

    /** @var list<array{string, float, string, string}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        config(['chronopost.product_codes.live_test_svc' => '99']);
        $this->app->instance(CarrierProductCodeResolver::class, new CarrierProductCodeResolver);
    }

    private function client(): ChronopostClient
    {
        $services = Mockery::mock(CarrierServiceRepository::class);
        $services->shouldReceive('enabledFor')->andReturn([
            ['code' => 'live_test_svc', 'label' => 'Service test', 'enabled' => true],
        ]);
        $grids = Mockery::mock(CarrierGridRepository::class);
        $grids->shouldReceive('forCarrier')->andReturn([]);
        $modes = Mockery::mock(PricingModeResolver::class);
        $modes->shouldReceive('getFor')->andReturn(PricingMode::LIVE_ONLY);

        $this->app->instance(CarrierServiceRepository::class, $services);
        $this->app->instance(CarrierGridRepository::class, $grids);
        $this->app->instance(PricingModeResolver::class, $modes);

        $soap = Mockery::mock(QuickCostSoapClient::class);
        $soap->shouldReceive('quickCost')->andReturnUsing(function (string $product, float $kg, string $dep, string $arr) {
            $this->calls[] = [$product, $kg, $dep, $arr];

            return new QuickCostResponse(
                serviceCode: $product,
                priceCentsHT: 1000,
                priceCentsTTC: 1200,
                priceCentsTVA: 200,
            );
        });

        return new ChronopostClient(
            config: [
                'max_weight_kg' => 30,
                'shipper' => ['zip' => '69007'],
                'services' => ['live_test_svc' => ['label' => 'Service test', 'enabled' => true]],
            ],
            livePricing: $this->app->make(LivePricingResolver::class),
            modes: $modes,
            liveClient: $soap,
        );
    }

    private function request(): QuoteRequest
    {
        return new QuoteRequest(weightKg: 3.0, destinationCountry: 'FR', destinationPostcode: '75001');
    }

    public function test_live_quote_injects_ht_in_default_ht_base(): void
    {
        Cache::put(self::SETTINGS_CACHE_KEY, ['shipping.tax.price_base' => 'ht']);

        $quotes = $this->client()->quote($this->request());

        $this->assertCount(1, $quotes);
        $this->assertSame(1000, $quotes[0]->priceCents);
        $this->assertSame('live_test_svc', $quotes[0]->serviceCode);
        $this->assertSame('Service test', $quotes[0]->serviceLabel);
    }

    public function test_live_quote_injects_ttc_when_price_base_is_ttc(): void
    {
        Cache::put(self::SETTINGS_CACHE_KEY, ['shipping.tax.price_base' => 'ttc']);

        $quotes = $this->client()->quote($this->request());

        $this->assertSame(1200, $quotes[0]->priceCents);
    }

    public function test_live_quote_sends_carrier_product_code_not_internal_slug(): void
    {
        Cache::put(self::SETTINGS_CACHE_KEY, ['shipping.tax.price_base' => 'ht']);

        $this->client()->quote($this->request());

        $this->assertSame([['99', 3.0, '69007', '75001']], $this->calls);
    }

    public function test_live_cache_is_keyed_by_price_base(): void
    {
        // Un seul client, cache conservé entre les devis : seul price_base change.
        $client = $this->client();

        Cache::put(self::SETTINGS_CACHE_KEY, ['shipping.tax.price_base' => 'ttc']);
        $this->assertSame(1200, $client->quote($this->request())[0]->priceCents);

        Cache::put(self::SETTINGS_CACHE_KEY, ['shipping.tax.price_base' => 'ht']);
        $this->assertSame(1000, $client->quote($this->request())[0]->priceCents);

        Cache::put(self::SETTINGS_CACHE_KEY, ['shipping.tax.price_base' => 'ttc']);
        $this->assertSame(1200, $client->quote($this->request())[0]->priceCents);

        // ttc et ht appelés une fois chacun ; le 3e devis (ttc) sort du cache.
        $this->assertCount(2, $this->calls);
    }
}
