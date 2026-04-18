<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Dto;

final class ShipmentRequest
{
    /**
     * @param  array<string, mixed>  $recipient  keys: name, company, street, zip, city, country, phone, email
     * @param  array<string, mixed>  $shipper  same keys as $recipient
     */
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderReference,
        public readonly float $weightKg,
        public readonly string $serviceCode,
        public readonly array $recipient,
        public readonly array $shipper,
    ) {}
}
