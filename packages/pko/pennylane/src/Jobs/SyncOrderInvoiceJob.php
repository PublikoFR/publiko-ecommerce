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
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Services\InvoiceSynchronizer;
use Throwable;

final class SyncOrderInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int,int> */
    public array $backoff = [60, 300, 900, 3600, 14400];

    public int $uniqueFor = 86400;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue((string) config('pennylane.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'pennylane-invoice-order-'.$this->orderId;
    }

    public function handle(InvoiceSynchronizer $synchronizer): void
    {
        $order = Order::with(['lines', 'customer', 'billingAddress', 'shippingAddress', 'transactions'])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('Pennylane sync: commande introuvable', ['order_id' => $this->orderId]);

            return;
        }

        $invoice = $synchronizer->sync($order);

        if ($invoice->isFinalized()) {
            $this->dispatchPendingCreditNotes($order);
        }
    }

    /**
     * Un remboursement passé avant la facture (facturation à l'expédition) n'a
     * pas pu produire son avoir : il est émis maintenant que la facture existe.
     */
    private function dispatchPendingCreditNotes(Order $order): void
    {
        if (! (bool) config('pennylane.auto_credit_note_on_refund', true)) {
            return;
        }

        $credited = PennylaneInvoice::query()
            ->where('order_id', $order->id)
            ->where('type', PennylaneInvoice::TYPE_CREDIT_NOTE)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->pluck('transaction_id')
            ->all();

        $order->transactions
            ->where('type', 'refund')
            ->where('success', true)
            ->whereNotIn('id', $credited)
            ->each(fn (Transaction $refund) => SyncRefundCreditNoteJob::dispatch($refund->id));
    }

    public function failed(Throwable $e): void
    {
        Log::error('Pennylane invoice job definitive failure', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);

        PennylaneInvoice::where('external_reference', 'order_'.$this->orderId)
            ->update([
                'status' => PennylaneInvoice::STATUS_FAILED,
                'last_error' => substr($e->getMessage(), 0, 5000),
            ]);
    }
}
