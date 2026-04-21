<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Tracking;

use RuntimeException;

class TrackingException extends RuntimeException
{
    public static function missingApiKey(): self
    {
        return new self('La Poste tracking API key missing (secret laposte.api_key).');
    }

    public static function unexpectedStatus(int $httpStatus, string $body): self
    {
        return new self("La Poste tracking API returned HTTP {$httpStatus}: {$body}");
    }

    public static function notFound(string $trackingNumber): self
    {
        return new self("Tracking number [{$trackingNumber}] not found by La Poste API.");
    }
}
