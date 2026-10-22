<?php

declare(strict_types=1);

namespace Pko\AiImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Pko\AiImporter\Enums\ErrorPolicy;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Notifications\ImportJobNotifier;
use Pko\AiImporter\Services\CollectionCatalog;
use Pko\AiImporter\Services\FeatureCatalog;
use Pko\AiImporter\Services\LunarBackupManager;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Services\ProgressCache;

/**
 * Reads validated staging rows and writes them to Lunar.
 *
 * A one-shot snapshot is taken through `LunarBackupManager::snapshot()` before
 * the first row is touched — this is what the admin "Rollback" button replays.
 *
 * The `error_policy` field on the job decides what happens after a row fails:
 *  - `ignore`   : log the error, continue on the next row
 *  - `stop`     : mark the job in error, leave the rest of staging untouched
 *  - `rollback` : restore the backup then mark the job as rolled_back
 */
class ImportStagingToLunarJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    /**
     * File et connexion fixées ici pour TOUS les points de dispatch : la file
     * dédiée est consommée par le worker planifié séparé (routes/console.php),
     * la connexion « longue » a un `retry_after` supérieur au timeout du job.
     */
    public function __construct(public readonly int $importJobId)
    {
        $this->onConnection(config('ai-importer.queues.connection'));
        $this->onQueue(config('ai-importer.queues.import', 'ai-importer-import'));
    }

    public function handle(LunarProductWriter $writer, LunarBackupManager $backup): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);

        // Les catégories ont pu changer depuis le parse (même process worker).
        CollectionCatalog::flush();
        FeatureCatalog::flush();

        // Honore les options de job : update_mode / row_filter / join_column / columns_to_import.
        $writer->configure($job->options)->setJobId($job->id);

        $job->update([
            'import_status' => ImportStatus::Importing,
            'import_started_at' => $job->import_started_at ?? now(),
            'stopped_by_user' => false,
        ]);

        $this->log($job, LogLevel::Info, 'Début de l\'import vers Lunar');

        if (! $job->backup_path) {
            $path = $backup->snapshot($job);
            $this->log($job, LogLevel::Info, 'Snapshot créé', ['path' => $path]);
        }

        $policy = $job->error_policy instanceof ErrorPolicy
            ? $job->error_policy
            : ErrorPolicy::from((string) $job->error_policy);

        $chunkEvery = (int) config('ai-importer.defaults.checkpoint_every', 100);
        $imported = (int) $job->imported_count;
        $errors = (int) $job->error_count;
        $flushSeconds = (float) config('ai-importer.defaults.log_flush_seconds', 2);
        $lastCheckpoint = microtime(true);

        try {
            StagingRecord::query()
                ->where('import_job_id', $job->id)
                ->whereIn('status', [StagingStatus::Pending->value, StagingStatus::Validated->value, StagingStatus::Warning->value])
                ->orderBy('id')
                ->chunkById(200, function ($records) use ($writer, $job, $policy, &$imported, &$errors, &$lastCheckpoint, $chunkEvery, $flushSeconds, $backup): bool {
                    foreach ($records as $record) {
                        try {
                            $writer->write($record);
                            // Le writer rejette certaines lignes sans lever
                            // d'exception (référence manquante, nom absent à la
                            // création) : elles comptent comme des erreurs.
                            if ($record->status === StagingStatus::Error) {
                                $errors++;
                            } else {
                                $imported++;
                            }
                            $this->logRow($job, $record, $imported + $errors);
                        } catch (\Throwable $e) {
                            $errors++;
                            $record->update([
                                'status' => StagingStatus::Error,
                                'error_message' => $e->getMessage(),
                            ]);
                            $this->log($job, LogLevel::Error, "Ligne #{$record->row_number}: {$e->getMessage()}", [
                                'staging_id' => $record->id,
                            ], $record->row_number);

                            if ($policy === ErrorPolicy::Stop) {
                                return false;
                            }

                            if ($policy === ErrorPolicy::Rollback) {
                                $backup->restore($job);
                                $job->update([
                                    'import_status' => ImportStatus::RolledBack,
                                    'rollback_completed' => true,
                                    'error_count' => $errors,
                                    'error_message' => $e->getMessage(),
                                ]);

                                return false;
                            }
                        }

                        // Checkpoint sur le nombre de lignes TRAITÉES (succès + erreurs) :
                        // compté sur les seuls succès, un lot d'erreurs consécutives
                        // n'aurait jamais rafraîchi la progression.
                        // Et au plus toutes les `log_flush_seconds` pour une barre fluide.
                        if (($imported + $errors) % $chunkEvery === 0 || microtime(true) - $lastCheckpoint >= $flushSeconds) {
                            $lastCheckpoint = microtime(true);
                            $job->update([
                                'imported_count' => $imported,
                                'error_count' => $errors,
                            ]);
                            ProgressCache::set($job, $imported, $job->staging_count);
                        }
                    }

                    return true;
                });

            // If rollback already transitioned us, leave it alone
            $fresh = $job->fresh();
            if ($fresh?->import_status === ImportStatus::RolledBack) {
                return;
            }

            $job->update([
                'import_status' => $errors > 0 && $policy === ErrorPolicy::Stop
                    ? ImportStatus::Error
                    : ImportStatus::Imported,
                'imported_count' => $imported,
                'error_count' => $errors,
                'import_completed_at' => now(),
            ]);
            ProgressCache::set($job, $imported, $job->staging_count);

            $this->log($job, LogLevel::Success, 'Import terminé', [
                'imported' => $imported,
                'errors' => $errors,
            ]);

            ImportJobNotifier::importCompleted($job->fresh());
        } catch (\Throwable $e) {
            DB::transaction(function () use ($job, $e): void {
                $job->update([
                    'import_status' => ImportStatus::Error,
                    'error_message' => $e->getMessage(),
                ]);
                $this->log($job, LogLevel::Error, 'Import interrompu: '.$e->getMessage());
            });
            ImportJobNotifier::importFailed($job->fresh(), $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        ImportJob::query()->where('id', $this->importJobId)->update([
            'import_status' => ImportStatus::Error,
            'error_message' => $e->getMessage(),
        ]);
    }

    /**
     * Une ligne de console par produit traité, écrite aussitôt : c'est ce qui
     * rend la progression lisible pendant l'import.
     */
    private function logRow(ImportJob $job, StagingRecord $record, int $position): void
    {
        $data = $record->data instanceof \ArrayObject ? $record->data->getArrayCopy() : (array) $record->data;
        $ref = (string) ($data['reference'] ?? '');
        $name = (string) ($data['name'] ?? '');
        $status = $record->status instanceof StagingStatus ? $record->status : StagingStatus::Imported;

        $total = $job->staging_count ?: '?';

        $level = match ($status) {
            StagingStatus::Error => LogLevel::Error,
            StagingStatus::Skipped => LogLevel::Warning,
            default => LogLevel::Info,
        };
        $outcome = $status === StagingStatus::Error && $record->error_message
            ? "{$status->label()} : {$record->error_message}"
            : $status->label();

        $this->log($job, $level, "Ligne {$position}/{$total} : [{$ref}] {$name} — {$outcome}", [], $record->row_number);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(ImportJob $job, LogLevel $level, string $message, array $context = [], ?int $rowNumber = null): void
    {
        ImportLog::create([
            'import_job_id' => $job->id,
            'phase' => LogPhase::Import,
            'row_number' => $rowNumber,
            'level' => $level,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
            'created_at' => now(),
        ]);
    }
}
