<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Models\PennylaneInvoice;

final class InvoiceSynchronizer
{
    public function __construct(
        private readonly CustomerMapper $customerMapper,
        private readonly OrderToInvoiceMapper $invoiceMapper,
        private readonly CustomerInvoicesResource $invoices,
    ) {}

    public function sync(Order $order): PennylaneInvoice
    {
        $externalReference = (string) config('pennylane.external_reference_prefix.invoice', 'order_').$order->id;

        $record = PennylaneInvoice::firstOrCreate(
            ['external_reference' => $externalReference],
            [
                'order_id' => $order->id,
                'type' => PennylaneInvoice::TYPE_INVOICE,
                'status' => PennylaneInvoice::STATUS_PENDING,
            ],
        );

        if ($record->isFinalized()) {
            return $record;
        }

        try {
            $pennylaneCustomerId = $this->customerMapper->resolveOrCreate($order);
            $dto = $this->invoiceMapper->build($order, $pennylaneCustomerId);

            $existing = $this->invoices->findByExternalReference($externalReference);

            if ($existing) {
                $pennylaneId = (int) $existing['id'];
                $status = $existing['status'] ?? 'draft';
                $invoiceNumber = $existing['invoice_number'] ?? null;

                if ($status !== 'finalized') {
                    $finalized = $this->invoices->finalize($pennylaneId);
                    $invoiceNumber = $finalized['invoice_number'] ?? $invoiceNumber;
                    $status = 'finalized';
                }
            } else {
                $created = $this->invoices->create($dto->toArray());
                $pennylaneId = (int) $created['id'];
                $status = $created['status'] ?? 'draft';
                $invoiceNumber = $created['invoice_number'] ?? null;

                if ($status !== 'finalized') {
                    $finalized = $this->invoices->finalize($pennylaneId);
                    $invoiceNumber = $finalized['invoice_number'] ?? $invoiceNumber;
                    $status = 'finalized';
                }
            }

            DB::transaction(function () use ($record, $pennylaneId, $invoiceNumber, $status, $dto): void {
                $record->update([
                    'pennylane_id' => $pennylaneId,
                    'pennylane_invoice_number' => $invoiceNumber,
                    'status' => $status === 'finalized'
                        ? PennylaneInvoice::STATUS_FINALIZED
                        : PennylaneInvoice::STATUS_DRAFT,
                    'payload_snapshot' => $dto->toArray(),
                    'last_error' => null,
                    'synced_at' => Carbon::now(),
                ]);
            });

            return $record->fresh();
        } catch (\Throwable $e) {
            Log::error('Pennylane invoice sync failed', [
                'order_id' => $order->id,
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
