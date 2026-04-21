<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pricing;

use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Dto\QuoteResponse;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Throwable;

/**
 * Orchestrates carrier pricing: live API call with 24 h cache and optional
 * fallback to the static grid repository. Responsibility of all live-vs-grid
 * logic is centralised here; Client SOAP implementations stay pure.
 */
class LivePricingResolver
{
    public const CACHE_TTL_SECONDS = 86400;

    public const CACHE_PREFIX = 'pko.shipping.';

    public const LOCK_SECONDS = 10;

    public function __construct(
        protected CarrierRegistry $carriers,
        protected CarrierGridRepository $grids,
        protected CarrierServiceRepository $services,
        protected PricingModeResolver $modes,
    ) {}

    /**
     * @param  callable(string $serviceCode, float $weightKg, string $depZip, string $arrZip): QuoteResponse  $livePricer
     *                                                                                                                     Closure that performs the live call for a single (service, weight, zip) combo and returns a QuoteResponse.
     * @return list<QuoteResponse>
     */
    public function resolveLive(string $carrier, QuoteRequest $request, callable $livePricer, string $depZip): array
    {
        $mode = $this->modes->getFor($carrier);

        if ($mode === PricingMode::GRID) {
            return $this->resolveFromGrid($carrier, $request);
        }

        $enabledServices = array_filter(
            $this->services->enabledFor($carrier),
            fn (array $s): bool => $request->serviceCodes === [] || in_array($s['code'], $request->serviceCodes, true),
        );

        if ($enabledServices === []) {
            return [];
        }

        $quotes = [];
        $anyFailed = false;

        foreach ($enabledServices as $service) {
            try {
                $quotes[] = $this->resolveCachedLive(
                    carrier: $carrier,
                    service: $service,
                    request: $request,
                    depZip: $depZip,
                    livePricer: $livePricer,
                );
            } catch (Throwable $e) {
                $anyFailed = true;
                $this->logLiveFailure($carrier, $service['code'], $e);

                if ($mode === PricingMode::LIVE_WITH_FALLBACK) {
                    $fallback = $this->fallbackQuoteFromGrid($carrier, $service, $request);
                    if ($fallback !== null) {
                        $quotes[] = $fallback;
                    }
                }
                // LIVE_ONLY → skip this service silently for the checkout
            }
        }

        if ($anyFailed) {
            Log::channel($this->logChannel())->info('live pricing completed with some failures', [
                'carrier' => $carrier,
                'mode' => $mode->value,
                'total_services' => count($enabledServices),
                'quotes_returned' => count($quotes),
            ]);
        }

        return $quotes;
    }

    /**
     * Pure grid-based pricing (current behaviour, no live call).
     *
     * @return list<QuoteResponse>
     */
    public function resolveFromGrid(string $carrier, QuoteRequest $request): array
    {
        $grid = $this->grids->forCarrier($carrier);
        if ($grid === []) {
            return [];
        }

        $priceCents = null;
        foreach ($grid as $bracket) {
            if ($request->weightKg <= (float) $bracket['max_kg']) {
                $priceCents = (int) $bracket['price'];
                break;
            }
        }

        if ($priceCents === null) {
            return [];
        }

        $enabledServices = array_filter(
            $this->services->enabledFor($carrier),
            fn (array $s): bool => $request->serviceCodes === [] || in_array($s['code'], $request->serviceCodes, true),
        );

        $out = [];
        foreach ($enabledServices as $service) {
            $out[] = new QuoteResponse(
                serviceCode: $service['code'],
                serviceLabel: $service['label'],
                priceCents: $priceCents,
            );
        }

        return $out;
    }

    /**
     * @param  callable(string, float, string, string): QuoteResponse  $livePricer
     */
    protected function resolveCachedLive(
        string $carrier,
        array $service,
        QuoteRequest $request,
        string $depZip,
        callable $livePricer,
    ): QuoteResponse {
        $weightBucket = (int) ceil(max(0.01, $request->weightKg));
        $arrPrefix = substr(preg_replace('/\D/', '', $request->destinationPostcode) ?? '', 0, 2);
        $cacheKey = self::CACHE_PREFIX.$carrier.'.quickcost.'.$service['code'].'.'.$depZip.'.'.$arrPrefix.'.'.$weightBucket;

        $store = $this->cacheStore($carrier);

        $cached = $store->get($cacheKey);
        if ($cached instanceof QuoteResponse) {
            return $cached;
        }

        // Light lock to avoid thundering-herd on fresh cache
        $lock = Cache::lock($cacheKey.':lock', self::LOCK_SECONDS);
        $lock->block(self::LOCK_SECONDS, function (): void {});

        try {
            $recheck = $store->get($cacheKey);
            if ($recheck instanceof QuoteResponse) {
                return $recheck;
            }

            $started = microtime(true);
            $response = $livePricer($service['code'], $request->weightKg, $depZip, $request->destinationPostcode);
            $durationMs = (int) ((microtime(true) - $started) * 1000);

            $store->put($cacheKey, $response, self::CACHE_TTL_SECONDS);

            Log::channel($this->logChannel())->info('live pricing cache miss', [
                'carrier' => $carrier,
                'service' => $service['code'],
                'weight_kg' => $request->weightKg,
                'arr_zip_prefix' => $arrPrefix,
                'duration_ms' => $durationMs,
            ]);

            return $response;
        } finally {
            optional($lock)->release();
        }
    }

    protected function fallbackQuoteFromGrid(string $carrier, array $service, QuoteRequest $request): ?QuoteResponse
    {
        $grid = $this->grids->forCarrier($carrier);
        foreach ($grid as $bracket) {
            if ($request->weightKg <= (float) $bracket['max_kg']) {
                return new QuoteResponse(
                    serviceCode: $service['code'],
                    serviceLabel: $service['label'],
                    priceCents: (int) $bracket['price'],
                );
            }
        }

        return null;
    }

    protected function cacheStore(string $carrier): Repository
    {
        $repo = Cache::store();

        // Use tags when the driver supports them so a "flush cache" UI action
        // can target this carrier only.
        if ($repo->getStore() instanceof TaggableStore) {
            return $repo->tags([self::CACHE_PREFIX.$carrier]);
        }

        return $repo;
    }

    public function flushCache(string $carrier): void
    {
        $repo = Cache::store();
        if ($repo->getStore() instanceof TaggableStore) {
            $repo->tags([self::CACHE_PREFIX.$carrier])->flush();
        }
    }

    protected function logLiveFailure(string $carrier, string $service, Throwable $e): void
    {
        Log::channel($this->logChannel())->warning('live pricing call failed', [
            'carrier' => $carrier,
            'service' => $service,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }

    protected function logChannel(): string
    {
        return config('logging.channels.shipping-quickcost') ? 'shipping-quickcost' : 'stack';
    }
}
