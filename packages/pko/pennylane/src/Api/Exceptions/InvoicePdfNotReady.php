<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api\Exceptions;

final class InvoicePdfNotReady extends PennylaneException
{
    public function __construct()
    {
        parent::__construct('Le PDF est en cours de préparation. Réessayez dans quelques minutes.');
    }
}
