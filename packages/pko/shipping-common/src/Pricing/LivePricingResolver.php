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
     * Pure grid-based pricing resolved per service.
     *
     * For each enabled service, the price bracket is resolved from the service-specific
     * rows first (preferred), then from null-service_code rows as a shared fallback
     * (Colissimo-style grids). A service with no matching bracket at the requested weight
     * is silently omitted — this is the natural masking mechanism (e.g. Chrono Relais
     * disappears above 20 kg when no 30 kg bracket exists for it).
     *
     * @return list<QuoteResponse>
     */
    public function resolveFromGrid(string $carrier, QuoteRequest $request): array
    {
        $enabledServices = array_filter(
            $this->services->enabledFor($carrier),
            fn (array $s): bool => $request->serviceCodes === [] || in_array($s['code'], $request->serviceCodes, true),
        );

        if ($enabledServices === []) {
            return [];
        }

        $out = [];
        foreach ($enabledServices as $service) {
            $priceCents = $this->resolvePriceForService($carrier, $service['code'], $request->weightKg);
            if ($priceCents === null) {
                continue;
            }
            $out[] = new QuoteResponse(
                serviceCode: $service['code'],
                serviceLabel: $service['label'],
                priceCents: $priceCents,
            );
        }

        return $out;
    }

    /**
     * Resolves the grid price for a single service at the given weight.
     *
     * Prefers a service-specific bracket over a null (shared) bracket. Takes the
     * lowest max_kg bracket that satisfies max_kg >= weightKg for each type.
     * Returns null when no bracket covers the weight (service should be masked).
     */
    private function resolvePriceForService(string $carrier, string $serviceCode, float $weightKg): ?int
    {
        $brackets = $this->grids->forCarrier($carrier, $serviceCode);

        $specificPrice = null;
        $fallbackPrice = null;

        foreach ($brackets as $bracket) {
            if ($weightKg > (float) $bracket['max_kg']) {
                continue;
            }
            if ($bracket['service_code'] === $serviceCode && $specificPrice === null) {
                $specificPrice = (int) $bracket['price'];
            } elseif ($bracket['service_code'] === null && $fallbackPrice === null) {
                $fallbackPrice = (int) $bracket['price'];
            }
            if ($specificPrice !== null && $fallbackPrice !== null) {
                break;
            }
        }

        return $specificPrice ?? $fallbackPrice;
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
        $priceCents = $this->resolvePriceForService($carrier, $service['code'], $request->weightKg);
        if ($priceCents === null) {
            return null;
        }

        return new QuoteResponse(
            serviceCode: $service['code'],
            serviceLabel: $service['label'],
            priceCents: $priceCents,
        );
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
