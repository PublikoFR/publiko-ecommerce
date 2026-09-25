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

    /**
     * Version du format mis en cache. À incrémenter dès que la forme des tableaux
     * renvoyés par PickupPointSoapClient change, sinon on relit pendant 3 h des
     * entrées de l'ancien format. v2 (2026-09-25) : horaires structurés + poidsMaxi.
     */
    private const CACHE_FORMAT_VERSION = 'v2';

    /**
     * Motif du dernier échec, exposé via lastSearchError().
     */
    private ?string $lastSearchError = null;

    public function __construct(
        private readonly PickupPointSoapClient $soapClient,
    ) {}

    /**
     * @return PickupPoint[]
     */
    public function search(
        string $postcode,
        string $countryCode = 'FR',
        ?string $serviceCode = null,
        ?string $city = null,
        ?int $weightGrams = null,
    ): array {
        $this->lastSearchError = null;

        // La ville entre dans la clé : elle change le jeu de points retourné.
        // Le poids, non : le WS ne filtre pas dessus (vérifié le 2026-09-25), le
        // filtre poidsMaxi est appliqué après lecture, cache compris.
        $cacheKey = 'chronopost_pickup:'.self::CACHE_FORMAT_VERSION.":{$postcode}:{$countryCode}:".($city ?? '');

        // Check cache first (only non-empty results are ever cached)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->filterByWeight($this->hydrate($cached), $weightGrams);
        }

        try {
            $rawPoints = $this->soapClient->search($postcode, $countryCode, $serviceCode, $city, $weightGrams);
        } catch (PickupPointException $e) {
            $this->lastSearchError = $e->getMessage();

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

        return $this->filterByWeight($this->hydrate($rawPoints), $weightGrams);
    }

    /**
     * Écarte les points dont le poids maximum accepté (poidsMaxi, 20 kg chez
     * Chronopost) est dépassé par le colis. Zéro point restant n'est pas une panne :
     * lastSearchError() reste null.
     *
     * @param  PickupPoint[]  $points
     * @return PickupPoint[]
     */
    private function filterByWeight(array $points, ?int $weightGrams): array
    {
        if ($weightGrams === null) {
            return $points;
        }

        return array_values(array_filter(
            $points,
            fn (PickupPoint $point): bool => $point->acceptsWeightGrams($weightGrams),
        ));
    }

    public function lastSearchError(): ?string
    {
        return $this->lastSearchError;
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
                openingSchedule: isset($data['opening_schedule']) && is_array($data['opening_schedule'])
                    ? array_values($data['opening_schedule'])
                    : null,
                maxWeightKg: isset($data['max_weight_kg']) ? (float) $data['max_weight_kg'] : null,
            );
        }

        return $points;
    }
}
