<?php

declare(strict_types=1);

namespace Pko\Pennylane\Observers;

use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Jobs\SyncOrderDeliveryNoteJob;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;
use Throwable;

final class OrderPennylaneObserver
{
    public function __construct(private readonly PennylaneClient $client) {}

    public function updated(Order $order): void
    {
        if (! $this->client->isConfigured()) {
            return;
        }

        if (! $order->wasChanged('status')) {
            return;
        }

        if ((bool) config('pennylane.delivery_note.enabled', false)
            && $order->status === (string) config('pennylane.delivery_note.trigger_on_status', 'dispatched')) {
            $this->dispatch(SyncOrderDeliveryNoteJob::class, $order);
        }

        if ($order->status !== (string) config('pennylane.trigger_on_status', 'payment-received')) {
            return;
        }

        // Même statut que le bon de livraison : c'est son job qui émet la
        // facture, une fois le bon créé, pour qu'elle puisse le citer.
        if (SyncOrderDeliveryNoteJob::chainsInvoice()) {
            return;
        }

        $this->dispatch(SyncOrderInvoiceJob::class, $order);
    }

    /**
     * @param  class-string<SyncOrderInvoiceJob|SyncOrderDeliveryNoteJob>  $job
     */
    private function dispatch(string $job, Order $order): void
    {
        try {
            $job::dispatch($order->id);
        } catch (Throwable $e) {
            // Avec QUEUE_CONNECTION=sync le job s'exécute dans la requête : une
            // erreur Pennylane ne doit jamais faire échouer le passage de commande.
            Log::error('Pennylane sync: dispatch échoué', [
                'order_id' => $order->id,
                'job' => class_basename($job),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
