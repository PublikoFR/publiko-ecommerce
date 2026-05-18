<?php

declare(strict_types=1);

namespace Pko\Pennylane\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Transaction;
use Pko\Pennylane\Services\CreditNoteSynchronizer;
use Throwable;

final class SyncRefundCreditNoteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 6;

    /** @var array<int,int> */
    public array $backoff = [60, 180, 600, 1800, 3600, 14400];

    public int $uniqueFor = 86400;

    public function __construct(public readonly int $transactionId)
    {
        $this->onQueue((string) config('pennylane.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'pennylane-credit-note-txn-'.$this->transactionId;
    }

    public function handle(CreditNoteSynchronizer $synchronizer): void
    {
        $txn = Transaction::with(['order.customer', 'order.billingAddress'])
            ->find($this->transactionId);

        if (! $txn) {
            Log::warning('Pennylane credit note: transaction introuvable', ['txn_id' => $this->transactionId]);

            return;
        }

        $result = $synchronizer->sync($txn);

        if ($result === null) {
            if ($this->attempts() < $this->tries) {
                $this->release(120);

                return;
            }

            Log::error('Pennylane credit note: facture parent jamais trouvée', [
                'transaction_id' => $this->transactionId,
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Pennylane credit note job definitive failure', [
            'transaction_id' => $this->transactionId,
            'error' => $e->getMessage(),
        ]);
    }
}
