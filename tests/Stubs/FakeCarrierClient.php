<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use Pko\ShippingCommon\Dto\ShipmentResponse;
use Throwable;

/**
 * Transporteur factice : enregistre les demandes d'étiquette et répond sans réseau.
 *
 * Hors d'une classe de test, sinon Pint renomme `testCredentials()` en
 * `test_credentials()` et l'interface n'est plus implémentée.
 */
final class FakeCarrierClient implements CarrierClient
{
    /** @var list<ShipmentRequest> */
    public array $requests = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
        private readonly string $trackingNumber = 'XN000000001FR',
    ) {}

    public function carrierCode(): string
    {
        return 'chronopost';
    }

    public function quote(QuoteRequest $request): array
    {
        return [];
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $this->requests[] = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new ShipmentResponse($this->trackingNumber, base64_encode('%PDF-1.4 fake'));
    }

    public function testCredentials(): bool
    {
        return true;
    }
}
