<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Dto;

final class ShipmentResponse
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $labelPdfBase64,
        public readonly array $rawResponse = [],
    ) {}
}
