<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Shipping;

final class ShipmentGroup
{
    public function __construct(
        public readonly string $origin,
        public readonly int $lineCount,
    ) {}
}
