<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

final class QuickCostResponse
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly int $priceCentsTTC,
        public readonly int $priceCentsHT,
        public readonly string $currency = 'EUR',
    ) {}
}
