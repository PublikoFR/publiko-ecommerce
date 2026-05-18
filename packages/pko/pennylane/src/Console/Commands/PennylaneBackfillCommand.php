<?php

declare(strict_types=1);

namespace Pko\Pennylane\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;
use Pko\Pennylane\Models\PennylaneInvoice;

final class PennylaneBackfillCommand extends Command
{
    protected $signature = 'pennylane:backfill
        {--since= : Date ISO (YYYY-MM-DD) à partir de laquelle backfiller}
        {--status= : Statut de commande à backfiller (défaut: valeur trigger_on_status)}
        {--limit=0 : Nombre max de commandes (0 = toutes)}';

    protected $description = 'Créer les factures Pennylane pour des commandes existantes non encore synchronisées.';

    public function handle(): int
    {
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))
            : null;
        $status = $this->option('status') ?: (string) config('pennylane.trigger_on_status', 'payment-received');
        $limit = (int) $this->option('limit');

        $query = Order::query()
            ->where('status', $status)
            ->whereDoesntHave('transactions', fn ($q) => $q->where('type', 'refund'))
            ->when($since, fn ($q) => $q->where('placed_at', '>=', $since))
            ->whereNotIn('id', PennylaneInvoice::query()
                ->where('type', PennylaneInvoice::TYPE_INVOICE)
                ->where('status', PennylaneInvoice::STATUS_FINALIZED)
                ->whereNotNull('order_id')
                ->pluck('order_id'));

        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $query->chunkById(100, function ($orders) use (&$count): void {
            foreach ($orders as $order) {
                SyncOrderInvoiceJob::dispatch($order->id);
                $count++;
            }
        });

        $this->info("Dispatché {$count} jobs de backfill Pennylane.");

        return self::SUCCESS;
    }
}
