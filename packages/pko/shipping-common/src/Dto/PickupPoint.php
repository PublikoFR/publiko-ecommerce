<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Dto;

/**
 * Point relais physique (Pickup) retourné par un PickupPointProvider.
 *
 * Représentation neutre, indépendante du transporteur : un provider Chronopost,
 * Mondial Relay, etc. mappe sa réponse propre vers ce DTO. Stocké tel quel
 * (via toArray()) dans le meta du panier/commande pour l'expédition.
 */
final class PickupPoint
{
    /**
     * @param  array<int, string>  $openingHours  Lignes d'horaires lisibles (optionnel)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $address1,
        public readonly string $postcode,
        public readonly string $city,
        public readonly string $countryCode = 'FR',
        public readonly ?string $carrier = null,
        public readonly ?float $distanceKm = null,
        public readonly array $openingHours = [],
    ) {}

    /**
     * @return array{id:string,name:string,address1:string,postcode:string,city:string,country_code:string,carrier:?string,distance_km:?float,opening_hours:array<int,string>}
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
            openingHours: array_values(array_map('strval', (array) ($data['opening_hours'] ?? []))),
        );
    }
}
