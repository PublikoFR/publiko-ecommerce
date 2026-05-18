<?php

declare(strict_types=1);

namespace Pko\Pennylane\Console\Commands;

use Illuminate\Console\Command;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;

final class PennylaneResyncOrderCommand extends Command
{
    protected $signature = 'pennylane:resync-order {order : ID de la commande Lunar} {--sync : Exécuter synchrone (debug)}';

    protected $description = 'Resynchroniser une commande Lunar vers Pennylane (créer ou re-finaliser la facture).';

    public function handle(): int
    {
        $orderId = (int) $this->argument('order');

        if ($this->option('sync')) {
            SyncOrderInvoiceJob::dispatchSync($orderId);
            $this->info("Synchronisation synchrone effectuée pour la commande {$orderId}.");
        } else {
            SyncOrderInvoiceJob::dispatch($orderId);
            $this->info("Job dispatché en queue pour la commande {$orderId}.");
        }

        return self::SUCCESS;
    }
}
