<?php

declare(strict_types=1);

namespace Pko\Pennylane\Observers;

use Lunar\Models\Order;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;

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

        SyncOrderInvoiceJob::dispatch($order->id);
    }
}
