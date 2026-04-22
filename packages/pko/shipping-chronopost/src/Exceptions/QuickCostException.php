<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Exceptions;

use RuntimeException;

class QuickCostException extends RuntimeException
{
    public static function missingCredentials(): self
    {
        return new self('Chronopost credentials missing for quickCost call.');
    }

    public static function soapFailure(string $message, ?\Throwable $previous = null): self
    {
        return new self('Chronopost quickCost SOAP failure: '.$message, 0, $previous);
    }

    public static function apiError(string $code, string $message): self
    {
        return new self("Chronopost quickCost returned error [{$code}]: {$message}");
    }
}
