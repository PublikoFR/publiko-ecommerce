<?php

declare(strict_types=1);

namespace Pko\Loyalty\Observers;

use Lunar\Models\Transaction;
use Pko\Loyalty\Services\LoyaltyManager;

/**
 * Retire les points de fidélité quand une commande est remboursée (partiel ou
 * total). Les avoirs Pennylane sont générés à partir de ces mêmes transactions
 * `refund` : un seul point d'entrée couvre remboursement et avoir.
 */
class RefundTransactionObserver
{
    public function __construct(protected LoyaltyManager $manager) {}

    public function saved(Transaction $transaction): void
    {
        if ($transaction->type !== 'refund' || ! $transaction->success) {
            return;
        }

        $order = $transaction->order;
        if ($order === null) {
            return;
        }

        $this->manager->revokeForRefunds($order);
    }
}
