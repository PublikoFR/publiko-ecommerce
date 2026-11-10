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
 * Doit mémoriser `pennylane_id` sur le versement dès la création distante
 * (avant toute finalisation), et lever une exception plutôt que finaliser un
 * document existant dont le montant diffère de `amountToIssue()`.
 */
interface BfaCreditNoteIssuer
{
    /**
     * @return array{id: int, number: string|null}
     */
    public function issue(BfaPayout $payout): array;
}
