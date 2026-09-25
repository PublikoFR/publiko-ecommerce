<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Dto;

/**
 * Point relais physique (Pickup) retourné par un PickupPointProvider.
 *
 * Représentation neutre, indépendante du transporteur : un provider Chronopost,
 * Mondial Relay, etc. mappe sa réponse propre vers ce DTO. Stocké tel quel
 * (via toArray()) dans le meta du panier/commande pour l'expédition.
 *
 * Les coordonnées géographiques (latitude/longitude) sont optionnelles : la carte
 * Leaflet n'affiche que les points qui les portent ; la liste est toujours autoritaire.
 *
 * Horaires : `openingHours` est le résumé lisible (« Lun–Ven 08:15-19:00 · Sam … »),
 * `openingSchedule` la version structurée (un élément par jour ouvert). Un
 * `openingSchedule` vide (`[]`) signifie « aucune contrainte horaire » — consigne
 * en accès libre ; `null` signifie « horaires inconnus » (saisie manuelle, provider
 * sans horaires). Ne jamais confondre les deux : cf. hasFreeAccess().
 */
final class PickupPoint
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $address1,
        public readonly string $postcode,
        public readonly string $city,
        public readonly string $countryCode = 'FR',
        public readonly ?string $carrier = null,
        public readonly ?float $distanceKm = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $openingHours = null,
        /** @var list<array{day: int, label: string, hours: string}>|null */
        public readonly ?array $openingSchedule = null,
        public readonly ?float $maxWeightKg = null,
    ) {}

    /**
     * Point sans aucune contrainte horaire (consigne libre-service).
     */
    public function hasFreeAccess(): bool
    {
        return $this->openingSchedule === [];
    }

    /**
     * Le point peut-il recevoir un colis de ce poids ? Poids ou limite inconnus → oui.
     */
    public function acceptsWeightGrams(?int $weightGrams): bool
    {
        if ($weightGrams === null || $this->maxWeightKg === null) {
            return true;
        }

        return $weightGrams <= (int) round($this->maxWeightKg * 1000);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address1' => $this->address1,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'country_code' => $this->countryCode,
            'carrier' => $this->carrier,
            'distance_km' => $this->distanceKm,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'opening_hours' => $this->openingHours,
            'opening_schedule' => $this->openingSchedule,
            'free_access' => $this->hasFreeAccess(),
            'max_weight_kg' => $this->maxWeightKg,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            address1: (string) ($data['address1'] ?? ''),
            postcode: (string) ($data['postcode'] ?? ''),
            city: (string) ($data['city'] ?? ''),
            countryCode: (string) ($data['country_code'] ?? 'FR'),
            carrier: isset($data['carrier']) ? (string) $data['carrier'] : null,
            distanceKm: isset($data['distance_km']) ? (float) $data['distance_km'] : null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            openingHours: isset($data['opening_hours']) ? (string) $data['opening_hours'] : null,
            openingSchedule: isset($data['opening_schedule']) && is_array($data['opening_schedule'])
                ? array_values($data['opening_schedule'])
                : null,
            maxWeightKg: isset($data['max_weight_kg']) ? (float) $data['max_weight_kg'] : null,
        );
    }
}
