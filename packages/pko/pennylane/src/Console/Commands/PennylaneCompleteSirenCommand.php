<?php

declare(strict_types=1);

namespace Pko\Pennylane\Console\Commands;

use Illuminate\Console\Command;
use Pko\Pennylane\Models\PennylaneCustomer;
use Pko\Pennylane\Services\CustomerMapper;

final class PennylaneCompleteSirenCommand extends Command
{
    protected $signature = 'pennylane:complete-siren';

    protected $description = 'Renseigner le SIREN des clients entreprise déjà créés dans Pennylane sans lui.';

    public function handle(CustomerMapper $mapper): int
    {
        $updated = 0;
        $failed = 0;

        PennylaneCustomer::query()
            ->whereNotNull('pennylane_customer_id')
            ->whereNotNull('lunar_customer_id')
            ->with('customer')
            ->chunkById(100, function ($mappings) use ($mapper, &$updated, &$failed): void {
                foreach ($mappings as $mapping) {
                    try {
                        if ($mapper->completeRegNo($mapping)) {
                            $updated++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->warn("Client Pennylane {$mapping->pennylane_customer_id} : {$e->getMessage()}");
                    }
                }
            });

        $this->info("SIREN renseigné sur {$updated} client(s) Pennylane, {$failed} échec(s).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
