<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
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
            Stat::make('Parse', $job->status->label())
                ->description(sprintf('%s / %s lignes — %d%%', $this->fmt($processed), $this->fmt($total), $pct))
                ->descriptionIcon('heroicon-o-document-arrow-down')
                ->color($job->status->color())
                ->chart($parseBar),

            Stat::make('Total', $this->fmt($counts['total']))
                ->description('lignes en staging')
                ->descriptionIcon('heroicon-o-table-cells')
                ->color('gray'),

            Stat::make('En attente', $this->fmt($counts['pending']))
                ->description('à importer')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Importé', $this->fmt($counts['imported']))
                ->description($job->import_status->label())
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
