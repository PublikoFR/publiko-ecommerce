<?php

declare(strict_types=1);

namespace Pko\Loyalty\Contracts;

use Pko\Loyalty\Models\BfaPayout;

/**
 * Émet l'avoir comptable d'un versement de BFA (avoir autonome, rattaché à
 * aucune facture). Implémenté par l'intégration comptable (pko/pennylane) ;
 * sans elle, `NullBfaCreditNoteIssuer` refuse l'émission.
 *
 * Doit être idempotent sur `$payout->external_reference` : un second appel
 * pour le même versement renvoie l'avoir existant au lieu d'en créer un autre.
 */
interface BfaCreditNoteIssuer
{
    /**
     * @return array{id: int, number: string|null}
     */
    public function issue(BfaPayout $payout): array;
}
