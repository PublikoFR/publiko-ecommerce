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

            // Reprise : une tentative précédente a pu créer la facture sans que
            // l'enregistrement local ait été mis à jour.
            $remote = $this->invoices->findByExternalReference($externalReference)
                ?? $this->invoices->create($dto->toArray());

            $pennylaneId = (int) $remote['id'];
            if (! CustomerInvoicesResource::isFinalized($remote)) {
                $remote = $this->invoices->finalize($pennylaneId);
            }

            $invoiceNumber = $remote['invoice_number'] ?? null;
            $status = 'finalized';

            $this->warnOnTotalMismatch($order, $remote);

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

    /**
     * Pennylane recalcule le total à partir des lignes HT et des taux : un
     * écart signale une remise ou un taux non repris, à corriger à la main.
     *
     * @param  array<string,mixed>  $remote
     */
    private function warnOnTotalMismatch(Order $order, array $remote): void
    {
        if (! isset($remote['currency_amount'])) {
            return;
        }

        $remoteCents = (int) round((float) $remote['currency_amount'] * 100);
        $orderCents = (int) $order->total->value;

        if (abs($remoteCents - $orderCents) > 1) {
            Log::warning('Pennylane : total de facture différent de la commande', [
                'order_id' => $order->id,
                'order_total_cents' => $orderCents,
                'pennylane_total_cents' => $remoteCents,
                'pennylane_id' => $remote['id'] ?? null,
            ]);
        }
    }
}
