<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Transaction;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Models\PennylaneInvoice;

final class CreditNoteSynchronizer
{
    public function __construct(
        private readonly CustomerMapper $customerMapper,
        private readonly TransactionToCreditNoteMapper $creditMapper,
        private readonly CustomerInvoicesResource $invoices,
    ) {}

    /**
     * @return PennylaneInvoice|null null si la facture parent n'est pas encore disponible (re-queue)
     */
    public function sync(Transaction $refund): ?PennylaneInvoice
    {
        $order = $refund->order;
        if (! $order) {
            throw new \RuntimeException("Transaction {$refund->id} sans commande.");
        }

        $parent = PennylaneInvoice::where('order_id', $order->id)
            ->where('type', PennylaneInvoice::TYPE_INVOICE)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->first();

        if (! $parent || ! $parent->pennylane_id) {
            return null;
        }

        $externalReference = (string) config('pennylane.external_reference_prefix.credit_note', 'refund_').$refund->id;

        $record = PennylaneInvoice::firstOrCreate(
            ['external_reference' => $externalReference],
            [
                'order_id' => $order->id,
                'transaction_id' => $refund->id,
                'parent_invoice_id' => $parent->id,
                'type' => PennylaneInvoice::TYPE_CREDIT_NOTE,
                'status' => PennylaneInvoice::STATUS_PENDING,
            ],
        );

        if ($record->isFinalized()) {
            return $record;
        }

        try {
            $pennylaneCustomerId = $this->customerMapper->resolveOrCreate($order);
            $dto = $this->creditMapper->build($refund, $pennylaneCustomerId, (int) $parent->pennylane_id);

            $existing = $this->invoices->findByExternalReference($externalReference);

            if ($existing) {
                $pennylaneId = (int) $existing['id'];
                $status = $existing['status'] ?? 'draft';
                $invoiceNumber = $existing['invoice_number'] ?? null;
            } else {
                $created = $this->invoices->create($dto->toArray());
                $pennylaneId = (int) $created['id'];
                $status = $created['status'] ?? 'draft';
                $invoiceNumber = $created['invoice_number'] ?? null;
            }

            if ($status !== 'finalized') {
                $finalized = $this->invoices->finalize($pennylaneId);
                $invoiceNumber = $finalized['invoice_number'] ?? $invoiceNumber;
                $status = 'finalized';
            }

            $record->update([
                'pennylane_id' => $pennylaneId,
                'pennylane_invoice_number' => $invoiceNumber,
                'status' => PennylaneInvoice::STATUS_FINALIZED,
                'payload_snapshot' => $dto->toArray(),
                'last_error' => null,
                'synced_at' => Carbon::now(),
            ]);

            return $record->fresh();
        } catch (\Throwable $e) {
            Log::error('Pennylane credit note sync failed', [
                'transaction_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);

            $record->update([
                'status' => PennylaneInvoice::STATUS_FAILED,
                'last_error' => substr($e->getMessage(), 0, 5000),
            ]);

            throw $e;
        }
    }
}
