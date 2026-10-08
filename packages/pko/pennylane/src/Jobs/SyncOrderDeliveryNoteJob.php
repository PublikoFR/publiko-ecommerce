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
use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Models\PennylaneDeliveryNote;
use Pko\Pennylane\Services\DeliveryNoteSynchronizer;
use Throwable;

/**
 * Émet le bon de livraison, puis la facture quand les deux partagent le même
 * statut déclencheur : la facture suit toujours la livraison qu'elle cite.
 */
final class SyncOrderDeliveryNoteJob implements ShouldBeUnique, ShouldQueue
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
        return 'pennylane-delivery-note-order-'.$this->orderId;
    }

    public function handle(DeliveryNoteSynchronizer $synchronizer): void
    {
        $order = Order::with(['lines', 'customer', 'billingAddress', 'shippingAddress'])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('Pennylane delivery note: commande introuvable', ['order_id' => $this->orderId]);

            return;
        }

        try {
            $synchronizer->sync($order);
        } catch (PennylaneApiException $e) {
            // Erreur définitive (scope `commercial_documents:all` absent,
            // payload refusé) : inutile de retarder la facture de 5 h de retries.
            if (! $e->isRetryable()) {
                $this->fail($e);

                return;
            }
            throw $e;
        }

        self::dispatchChainedInvoice($this->orderId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Pennylane delivery note job definitive failure', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);

        PennylaneDeliveryNote::where('order_id', $this->orderId)
            ->update([
                'status' => PennylaneDeliveryNote::STATUS_FAILED,
                'last_error' => substr($e->getMessage(), 0, 5000),
            ]);

        // Un bon de livraison en échec ne bloque pas la facturation : il se
        // relance depuis la fiche commande.
        self::dispatchChainedInvoice($this->orderId);
    }

    /**
     * Vrai quand facture et bon de livraison partent sur le même statut :
     * l'observer laisse alors ce job déclencher la facture.
     */
    public static function chainsInvoice(): bool
    {
        return (bool) config('pennylane.delivery_note.enabled', false)
            && (string) config('pennylane.delivery_note.trigger_on_status', 'dispatched')
                === (string) config('pennylane.trigger_on_status', 'payment-received');
    }

    private static function dispatchChainedInvoice(int $orderId): void
    {
        if (self::chainsInvoice()) {
            SyncOrderInvoiceJob::dispatch($orderId);
        }
    }
}
