<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Dto;

final class ShipmentRequest
{
    /**
     * @param  string  $serviceCode  Code interne (chrono13, chrono_relais, DOM…) — sert au suivi et aux grilles.
     * @param  string|null  $carrierProductCode  Code produit attendu par le WS transporteur (1, 2, 86, DOM…).
     *                                           NULL = utiliser $serviceCode tel quel.
     * @param  array<string, mixed>  $recipient  keys: name, company, street, zip, city, country, phone, email
     * @param  array<string, mixed>  $shipper  same keys as $recipient
     * @param  array{length: float, width: float, height: float}|null  $dimensionsCm
     * @param  string|null  $carrierService  Code service transporteur (Chronopost `<service>` : `0` semaine,
     *                                       `6` samedi). NULL = défaut du transporteur. Non exposé au checkout.
     */
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderReference,
        public readonly float $weightKg,
        public readonly string $serviceCode,
        public readonly array $recipient,
        public readonly array $shipper,
        public readonly ?string $pickupPointId = null,
        public readonly ?string $carrierProductCode = null,
        public readonly ?array $dimensionsCm = null,
        public readonly int $parcelCount = 1,
        public readonly ?string $carrierService = null,
    ) {}

    /**
     * Code produit à envoyer au transporteur, avec repli sur le code de service.
     */
    public function productCode(): string
    {
        return ($this->carrierProductCode !== null && $this->carrierProductCode !== '')
            ? $this->carrierProductCode
            : $this->serviceCode;
    }
}
