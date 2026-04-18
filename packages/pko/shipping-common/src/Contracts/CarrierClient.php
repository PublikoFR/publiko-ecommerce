<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Contracts;

use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Dto\QuoteResponse;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use Pko\ShippingCommon\Dto\ShipmentResponse;

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
