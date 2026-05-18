<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api\Exceptions;

final class PennylaneNotConfiguredException extends PennylaneException
{
    public static function missingToken(): self
    {
        return new self('Pennylane API token manquant. Configurez PENNYLANE_API_TOKEN.');
    }

    public static function missingTemplate(): self
    {
        return new self('Pennylane customer_invoice_template_id manquant. Configurez PENNYLANE_INVOICE_TEMPLATE_ID.');
    }
}
