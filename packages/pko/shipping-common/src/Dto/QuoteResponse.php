<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Dto;

final class QuoteResponse
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly string $serviceLabel,
        public readonly int $priceCents,
        public readonly string $currencyCode = 'EUR',
        public readonly ?string $estimatedDelivery = null,
    ) {}
}
