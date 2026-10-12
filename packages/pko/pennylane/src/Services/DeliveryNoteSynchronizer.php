<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Pko\Pennylane\Api\Resources\CommercialDocumentsResource;
use Pko\Pennylane\Models\PennylaneDeliveryNote;

final class DeliveryNoteSynchronizer
{
    public function __construct(
        private readonly CustomerMapper $customerMapper,
        private readonly OrderToDeliveryNoteMapper $mapper,
        private readonly CommercialDocumentsResource $documents,
    ) {}

    public function sync(Order $order): PennylaneDeliveryNote
    {
        $externalReference = OrderToDeliveryNoteMapper::externalReference($order);

        $record = PennylaneDeliveryNote::firstOrCreate(
            ['external_reference' => $externalReference],
            ['order_id' => $order->id, 'status' => PennylaneDeliveryNote::STATUS_PENDING],
        );

        if ($record->isFinalized()) {
            return $record;
        }

        try {
            $pennylaneCustomerId = $this->customerMapper->resolveOrCreate($order);
            $dto = $this->mapper->build($order, $pennylaneCustomerId);

            // Reprise : une tentative précédente a pu créer le document sans
            // que l'enregistrement local ait été mis à jour.
            $remote = $this->documents->findByExternalReference($externalReference, $pennylaneCustomerId, $dto->documentType)
                ?? $this->documents->create($dto->toArray());

            $record->update([
                'pennylane_id' => (int) $remote['id'],
                'pennylane_document_number' => $remote['document_number'] ?? null,
                'status' => PennylaneDeliveryNote::STATUS_FINALIZED,
                'payload_snapshot' => $dto->toArray(),
                'last_error' => null,
                'synced_at' => Carbon::now(),
            ]);

            return $record->fresh();
        } catch (\Throwable $e) {
            Log::error('Pennylane delivery note sync failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $record->update([
                'status' => PennylaneDeliveryNote::STATUS_FAILED,
                'last_error' => substr($e->getMessage(), 0, 5000),
            ]);

            throw $e;
        }
    }
}
