<?php

declare(strict_types=1);

namespace Pko\Pennylane\Observers;

use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Pko\Pennylane\Api\PennylaneClient;
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

        $targetStatus = (string) config('pennylane.trigger_on_status', 'payment-received');

        if ($order->status !== $targetStatus) {
            return;
        }

        try {
            SyncOrderInvoiceJob::dispatch($order->id);
        } catch (Throwable $e) {
            // Avec QUEUE_CONNECTION=sync le job s'exécute dans la requête : une
            // erreur Pennylane ne doit jamais faire échouer le passage de commande.
            Log::error('Pennylane sync: dispatch échoué', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
