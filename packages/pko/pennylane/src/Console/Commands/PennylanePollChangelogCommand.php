<?php

declare(strict_types=1);

namespace Pko\Pennylane\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Models\PennylaneInvoice;

final class PennylanePollChangelogCommand extends Command
{
    protected $signature = 'pennylane:poll-changelog {--since= : Date ISO à partir de laquelle poller (défaut: dernière synchro)}';

    protected $description = 'Poll Pennylane changelog pour mettre à jour les statuts locaux des factures.';

    public function handle(CustomerInvoicesResource $invoices, PennylaneClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->warn('Pennylane non configuré (PENNYLANE_API_TOKEN manquant).');

            return self::SUCCESS;
        }

        if ($this->option('since')) {
            $since = Carbon::parse($this->option('since'))->toRfc3339String();
        } else {
            $lastSync = PennylaneInvoice::max('synced_at');
            $since = $lastSync
                ? Carbon::parse($lastSync)->subMinutes(30)->toRfc3339String()
                : Carbon::now()->subHour()->toRfc3339String();
        }

        $cursor = null;
        $processed = 0;

        do {
            $page = $invoices->changelog(startDate: $cursor ? null : $since, cursor: $cursor, limit: 200);

            foreach ((array) ($page['items'] ?? []) as $item) {
                $pennylaneId = $item['id'] ?? null;
                if (! $pennylaneId) {
                    continue;
                }

                $record = PennylaneInvoice::where('pennylane_id', $pennylaneId)->first();
                if (! $record) {
                    continue;
                }

                try {
                    $remote = $invoices->get((int) $pennylaneId);
                    $record->update([
                        'pennylane_invoice_number' => $remote['invoice_number'] ?? $record->pennylane_invoice_number,
                        'status' => ($remote['status'] ?? 'draft') === 'finalized'
                            ? PennylaneInvoice::STATUS_FINALIZED
                            : PennylaneInvoice::STATUS_DRAFT,
                        'synced_at' => Carbon::now(),
                    ]);
                    $processed++;
                } catch (\Throwable $e) {
                    $this->warn("Erreur fetch invoice {$pennylaneId}: ".$e->getMessage());
                }
            }

            $cursor = $page['next_cursor'] ?? null;
        } while (! empty($page['has_more']) && $cursor);

        $this->info("Poll changelog: {$processed} factures mises à jour.");

        return self::SUCCESS;
    }
}
