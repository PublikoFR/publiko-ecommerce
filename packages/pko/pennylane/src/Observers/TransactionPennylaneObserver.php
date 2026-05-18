<?php

declare(strict_types=1);

namespace Pko\Pennylane\Observers;

use Lunar\Models\Transaction;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Jobs\SyncRefundCreditNoteJob;

final class TransactionPennylaneObserver
{
    public function __construct(private readonly PennylaneClient $client) {}

    public function created(Transaction $transaction): void
    {
        if (! $this->client->isConfigured()) {
            return;
        }

        if (! (bool) config('pennylane.auto_credit_note_on_refund', true)) {
            return;
        }

        if ($transaction->type !== 'refund') {
            return;
        }

        if (! $transaction->success) {
            return;
        }

        SyncRefundCreditNoteJob::dispatch($transaction->id);
    }
}
