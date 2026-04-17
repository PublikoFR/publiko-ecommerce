<?php

declare(strict_types=1);

namespace Mde\AiImporter\Console;

use Illuminate\Console\Command;
use Mde\AiImporter\Enums\ImportStatus;
use Mde\AiImporter\Enums\JobStatus;
use Mde\AiImporter\Jobs\ImportStagingToLunarJob;
use Mde\AiImporter\Models\ImportJob;

/**
 * Dispatches `ImportStagingToLunarJob` for every job that reached
 * `status=parsed` and has a `scheduled_at` in the past (or now).
 *
 * Meant to be called every few minutes by the Laravel scheduler —
 * see `routes/console.php`. Manually triggerable with `make artisan
 * CMD='ai-importer:run-scheduled'` to sanity-check a scheduled batch
 * before waiting for the cron tick.
 */
class RunScheduledImportsCommand extends Command
{
    protected $signature = 'ai-importer:run-scheduled {--dry : List what would run without dispatching}';

    protected $description = 'Lance les imports Lunar pour les jobs parsed dont scheduled_at <= maintenant.';

    public function handle(): int
    {
        $due = ImportJob::query()
            ->where('status', JobStatus::Parsed->value)
            ->whereIn('import_status', [ImportStatus::Pending->value, ImportStatus::Scheduled->value])
            ->where(function ($q): void {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->whereNotNull('scheduled_at') // scheduler only picks jobs EXPLICITLY scheduled
            ->orderBy('scheduled_at')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Aucun job programmé dû.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry');

        foreach ($due as $job) {
            $this->line(sprintf(
                '%s #%d — %s (scheduled %s)',
                $dry ? '[dry] ' : '→ dispatch',
                $job->id,
                $job->uuid,
                $job->scheduled_at?->diffForHumans() ?? 'now',
            ));

            if ($dry) {
                continue;
            }

            $job->update(['import_status' => ImportStatus::Scheduled]);
            ImportStagingToLunarJob::dispatch($job->id)
                ->onQueue(config('ai-importer.queues.import', 'ai-importer-import'));
        }

        $this->info(($dry ? 'DRY — ' : '').$due->count().' job(s) traité(s).');

        return self::SUCCESS;
    }
}
