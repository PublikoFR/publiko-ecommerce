<?php

declare(strict_types=1);

namespace Pko\Loyalty\Services;

use Pko\Loyalty\Contracts\BfaCreditNoteIssuer;
use Pko\Loyalty\Models\BfaPayout;

final class NullBfaCreditNoteIssuer implements BfaCreditNoteIssuer
{
    public function issue(BfaPayout $payout): array
    {
        throw new \RuntimeException('Aucune intégration comptable n\'est installée pour émettre les avoirs.');
    }
}
