<?php

declare(strict_types=1);

namespace Mde\ShippingCommon\Contracts;

use Mde\ShippingCommon\Dto\QuoteRequest;
use Mde\ShippingCommon\Dto\QuoteResponse;
use Mde\ShippingCommon\Dto\ShipmentRequest;
use Mde\ShippingCommon\Dto\ShipmentResponse;

interface CarrierClient
{
    public function carrierCode(): string;

    /**
     * @return list<QuoteResponse>
     */
    public function quote(QuoteRequest $request): array;

    public function createShipment(ShipmentRequest $request): ShipmentResponse;

    public function testCredentials(): bool;
}
