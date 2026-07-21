<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CatalogAvailability;
use Illuminate\Console\Command;

/**
 * Rattrapage ciblé des pivots de visibilité catalogue par groupe client.
 *
 * Ne supprime QUE les lignes portant la signature du semis automatique de Lunar
 * (cf. App\Support\CatalogAvailability::seededRows() pour les trois marqueurs).
 * Toute ligne saisie à la main — une vraie restriction — est épargnée.
 *
 * Dry-run par défaut : sans `--apply`, la commande se contente de rapporter.
 * Idempotente : un second passage ne supprime rien.
 */
class PruneCatalogAvailability extends Command
{
    protected $signature = 'pko:catalog-availability:prune
        {--apply : Supprimer réellement (sans ce flag, simple rapport)}';

    protected $description = 'Supprime les lignes de visibilité catalogue semées automatiquement par Lunar';

    public function handle(): int
    {
        $audit = CatalogAvailability::auditSeededRows();

        $rows = [];
        $totalSeeded = 0;
        $totalKept = 0;

        foreach ($audit as $table => $counts) {
            $kept = $counts['total'] - $counts['seeded'];
            $totalSeeded += $counts['seeded'];
            $totalKept += $kept;

            $rows[] = [$table, $counts['total'], $counts['seeded'], $kept];
        }

        $this->table(['Table', 'Total', 'Semées (à supprimer)', 'Conservées'], $rows);

        if ($totalKept > 0) {
            $this->warn(
                "{$totalKept} ligne(s) ne portent pas la signature du semis et seront CONSERVÉES ".
                '— ce sont des restrictions saisies manuellement.'
            );
        }

        if ($totalSeeded === 0) {
            $this->info('Aucune ligne semée à supprimer.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->comment("Simulation. Relancer avec --apply pour supprimer ces {$totalSeeded} ligne(s).");

            return self::SUCCESS;
        }

        $deleted = CatalogAvailability::deleteSeededRows();

        foreach ($deleted as $table => $n) {
            $this->line("  {$table} : {$n} ligne(s) supprimée(s)");
        }

        $this->info('Terminé. Toute ligne restante est une restriction explicite.');

        return self::SUCCESS;
    }
}
