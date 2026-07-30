<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pko\ShippingChronopost\Exceptions\PickupPointException;
use Pko\ShippingCommon\Contracts\PickupPointProvider;
use Pko\ShippingCommon\Dto\PickupPoint;

/**
 * Chrono Relais pickup point provider backed by Chronopost PointRelaisServiceWS.
 *
 * Cache strategy: successful results cached per postcode for a few hours to avoid
 * repeated SOAP calls during checkout. Errors are NOT cached — a transient SOAP
 * fault falls back to [] without poisoning the cache for subsequent requests.
 */
final class ChronopostPickupPointProvider implements PickupPointProvider
{
    private const CACHE_TTL_SECONDS = 10800; // 3 hours

    public function __construct(
        private readonly PickupPointSoapClient $soapClient,
    ) {}

    /**
     * @return PickupPoint[]
     */
    public function search(string $postcode, string $countryCode = 'FR', ?string $serviceCode = null): array
    {
        $cacheKey = "chronopost_pickup:{$postcode}:{$countryCode}";

        // Check cache first (only non-empty results are ever cached)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->hydrate($cached);
        }

        try {
            $rawPoints = $this->soapClient->search($postcode, $countryCode, $serviceCode);
        } catch (PickupPointException $e) {
            Log::channel('shipping-pickup')->error('Chronopost pickup point search failed', [
                'postcode' => $postcode,
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (empty($rawPoints)) {
            return [];
        }

        // Cache only non-empty successes
        Cache::put($cacheKey, $rawPoints, self::CACHE_TTL_SECONDS);

        return $this->hydrate($rawPoints);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawPoints
     * @return PickupPoint[]
     */
    private function hydrate(array $rawPoints): array
    {
        $points = [];
        foreach ($rawPoints as $data) {
            if (empty($data['id'])) {
                continue;
            }
            $points[] = new PickupPoint(
                id: $data['id'],
                name: $data['name'] ?? '',
                address1: $data['address1'] ?? '',
                postcode: $data['postcode'] ?? '',
                city: $data['city'] ?? '',
                countryCode: $data['country_code'] ?? 'FR',
                distanceKm: isset($data['distance_km']) ? (float) $data['distance_km'] : null,
                latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
                longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
                openingHours: $data['opening_hours'] ?? null,
            );
        }

        return $points;
    }
}
