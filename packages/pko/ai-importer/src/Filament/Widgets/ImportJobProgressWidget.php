<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Services\ProgressCache;

/**
 * Live progress widget on the ViewImportJob page.
 *
 * Reads from `ProgressCache` (Redis) instead of the DB so that a 2 s poll
 * across a dozen open admin tabs doesn't hammer MySQL. Falls back to the
 * DB counter when the cache key has expired or the job hasn't started yet.
 */
class ImportJobProgressWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '2s';

    protected int|string|array $columnSpan = 'full';

    public ?ImportJob $record = null;

    protected function getStats(): array
    {
        $job = $this->record;
        if (! $job) {
            return [];
        }

        $progress = ProgressCache::get($job);
        $processed = $progress['processed'] ?? $job->processed_rows;
        $total = $progress['total'] ?? $job->total_rows;
        $pct = $progress['percentage'] ?? $job->progressPercentage();

        $parseBar = $this->bar($pct);
        $counts = $job->stagingStatusCounts();

        return [
            Stat::make('Préparation', $job->status->label())
                ->description(sprintf('%s / %s lignes — %d%%', $this->fmt($processed), $this->fmt($total), $pct))
                ->descriptionIcon('heroicon-o-document-arrow-down')
                ->color($job->status->color())
                ->chart($parseBar),

            Stat::make('Import', $job->import_status->label())
                ->description($this->importHint($job))
                ->descriptionIcon($this->importIcon($job))
                ->color($job->import_status->color()),

            Stat::make('Total', $this->fmt($counts['total']))
                ->description('lignes en staging')
                ->descriptionIcon('heroicon-o-table-cells')
                ->color('gray'),

            Stat::make('En attente', $this->fmt($counts['pending']))
                ->description('à importer')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Importé', $this->fmt($counts['imported']))
                ->description('lignes écrites dans Lunar')
                ->descriptionIcon('heroicon-o-arrow-down-on-square-stack')
                ->color('success'),

            Stat::make('Avertissements', $this->fmt($counts['warning']))
                ->description('lignes à vérifier')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning'),

            Stat::make('Erreurs', $this->fmt($counts['error']))
                ->description('lignes en échec')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }

    /**
     * Message d'étape clair pour la phase d'import (ce qui se passe / ce que
     * l'admin doit faire ensuite).
     */
    private function importHint(ImportJob $job): string
    {
        return match ($job->import_status) {
            ImportStatus::Pending => 'Validez le staging, puis « Programmer l\'import »',
            ImportStatus::Scheduled => $job->scheduled_at && $job->scheduled_at->isFuture()
                ? 'En attente du CRON — prévu '.$job->scheduled_at->diffForHumans()
                : 'En attente du CRON (prochain passage ≤ 2 min)',
            ImportStatus::Queued => 'Pris par le CRON — démarrage imminent',
            ImportStatus::Importing => 'Écriture dans Lunar en cours…',
            ImportStatus::Imported => 'Import terminé',
            ImportStatus::Error => 'Échec — voir les logs ci-dessous',
            ImportStatus::RolledBack => 'Import annulé (rollback effectué)',
        };
    }

    private function importIcon(ImportJob $job): string
    {
        return match ($job->import_status) {
            ImportStatus::Pending => 'heroicon-o-hand-raised',
            ImportStatus::Scheduled, ImportStatus::Queued => 'heroicon-o-clock',
            ImportStatus::Importing => 'heroicon-o-arrow-path',
            ImportStatus::Imported => 'heroicon-o-check-circle',
            ImportStatus::Error => 'heroicon-o-x-circle',
            ImportStatus::RolledBack => 'heroicon-o-arrow-uturn-left',
        };
    }

    private function fmt(?int $n): string
    {
        return $n === null ? '—' : number_format($n, 0, ',', ' ');
    }

    /**
     * @return array<int, int> fake sparkline based on current progress
     */
    private function bar(int $pct): array
    {
        return [0, max(0, (int) round($pct / 4)), max(0, (int) round($pct / 2)), max(0, (int) round($pct * 0.75)), $pct];
    }
}
