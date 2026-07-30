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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'address1'      => $this->address1,
            'postcode'      => $this->postcode,
            'city'          => $this->city,
            'country_code'  => $this->countryCode,
            'carrier'       => $this->carrier,
            'distance_km'   => $this->distanceKm,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'opening_hours' => $this->openingHours,
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
        );
    }
}
