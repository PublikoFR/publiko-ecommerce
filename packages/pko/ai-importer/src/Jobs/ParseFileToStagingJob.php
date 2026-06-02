<?php

declare(strict_types=1);

namespace Pko\AiImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Enums\ErrorPolicy;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Notifications\ImportJobNotifier;
use Pko\AiImporter\Services\ActionPipeline;
use Pko\AiImporter\Services\ProgressCache;
use Pko\AiImporter\Services\SpreadsheetParser;

/**
 * Parses the uploaded spreadsheet and populates `pko_ai_importer_staging`.
 *
 * Iteration is resumable: on failure, `last_processed_row` stores the row
 * number of the last row flushed to staging. Re-dispatching the job picks
 * up right after that row. The `row_limit` option caps the number of rows
 * for test runs.
 *
 * Secondary sheets referenced in `config.sheets` are indexed per-row by
 * `join_key` and exposed through `ExecutionContext::$sheets` so that
 * `multiline_aggregate` actions can resolve 1-N relationships.
 */
class ParseFileToStagingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    public function __construct(public readonly int $importJobId) {}

    public function handle(SpreadsheetParser $parser, ActionPipeline $pipeline): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);

        if (! $job->config) {
            $this->fail($job, 'No config attached to job');

            return;
        }

        $job->update([
            'status' => JobStatus::Parsing,
            'parse_started_at' => $job->parse_started_at ?? now(),
        ]);

        $this->log($job, LogLevel::Info, 'Début du parse', ['file' => $job->input_file_path]);

        try {
            $disk = Storage::disk(config('ai-importer.storage.disk', 'local'));
            $absolutePath = $disk->path($job->input_file_path);

            $config = $job->config->config_data->getArrayCopy();
            // Stream XLSX/XLS past 10k rows; CSV is always streamed by PhpSpreadsheet anyway.
            $fileSize = @filesize($absolutePath) ?: 0;
            $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            if (in_array($ext, ['xlsx', 'xls'], true) && $fileSize > 5 * 1024 * 1024) {
                $parser->loadStreamed($absolutePath, $config, (int) ($job->chunk_size ?: 2000));
            } else {
                $parser->load($absolutePath, $config);
            }

            $primary = $parser->primarySheetName();
            $total = $parser->countRows($primary);
            $job->update(['total_rows' => $total]);

            $joinKey = $parser->joinKeyName();
            $mapping = (array) ($config['mapping'] ?? []);

            // Option `columns_to_process` : restreint le mapping à un sous-ensemble
            // de clés (les autres ne sont pas calculées au parse). Vide = tout.
            $columnsToProcess = array_values(array_map(
                'strval',
                (array) ($job->options['columns_to_process'] ?? []),
            ));
            if ($columnsToProcess !== []) {
                $mapping = array_intersect_key($mapping, array_flip($columnsToProcess));
            }
            $chunkEvery = (int) config('ai-importer.defaults.checkpoint_every', 100);
            $rowLimit = $job->row_limit;
            $policy = $job->error_policy instanceof ErrorPolicy
                ? $job->error_policy
                : ErrorPolicy::from((string) $job->error_policy);

            // Sémantique de reprise unique : `last_processed_row` = nombre de
            // lignes DATA déjà traitées (succès OU erreur). On le passe tel
            // quel à iterateRows() qui l'interprète comme un offset de
            // comptage (cf. SpreadsheetParser::iterateRows). « Relancer le
            // parse » reprend ainsi exactement après la dernière ligne traitée,
            // sans saut ni doublon.
            $startAfter = (int) ($job->last_processed_row ?? 0);
            $processed = $startAfter;
            $stagingCount = (int) $job->staging_count;
            $errors = 0;
            $stopped = false;

            foreach ($parser->iterateRows($primary, $startAfter) as $rowNumber => $row) {
                if ($rowLimit !== null && $processed >= $rowLimit) {
                    break;
                }

                // Isolation par ligne : une ligne en erreur (action mal formée,
                // échec LLM, cellule invalide…) ne doit JAMAIS avorter tout le
                // job. Elle est journalisée, persistée en staging au statut
                // Error, puis le `error_policy` décide de continuer ou d'arrêter.
                $mapped = [];
                $halt = false;

                try {
                    $sheets = $joinKey !== null
                        ? $parser->secondarySheetsFor($row[$joinKey] ?? '')
                        : [];

                    $ctx = new ExecutionContext(
                        job: $job,
                        row: $row,
                        sheets: $sheets,
                        rowNumber: $rowNumber,
                    );

                    foreach ($mapping as $outputKey => $columnConfig) {
                        $srcCol = $columnConfig['col'] ?? null;
                        $srcSheet = $columnConfig['sheet'] ?? null;

                        // Résolution de la valeur initiale selon la feuille source :
                        //  - feuille primaire (ou absente)  → colonne de la row courante.
                        //  - feuille secondaire relation=one → colonne de l'unique row jointe.
                        //  - feuille secondaire relation=many → laissée à `multiline_aggregate`.
                        if ($srcCol === null) {
                            $initial = null;
                        } elseif ($srcSheet === null || $srcSheet === '' || $srcSheet === $primary) {
                            $initial = $row[$srcCol] ?? null;
                        } else {
                            $relation = (string) ($config['sheets'][$srcSheet]['relation'] ?? 'many');
                            $initial = $relation === 'one'
                                ? (($sheets[$srcSheet][0] ?? [])[$srcCol] ?? null)
                                : null;
                        }

                        $value = $pipeline->run($initial, (array) $columnConfig, $ctx);
                        $mapped[$outputKey] = $value;
                        $ctx->setOutput((string) $outputKey, $value);
                    }

                    // Anti-doublon à la reprise : updateOrCreate sur la clé
                    // unique (import_job_id, row_number).
                    $record = StagingRecord::updateOrCreate(
                        ['import_job_id' => $job->id, 'row_number' => $rowNumber],
                        ['data' => $mapped, 'status' => StagingStatus::Pending, 'error_message' => null],
                    );
                    if ($record->wasRecentlyCreated) {
                        $stagingCount++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $record = StagingRecord::updateOrCreate(
                        ['import_job_id' => $job->id, 'row_number' => $rowNumber],
                        ['data' => $mapped, 'status' => StagingStatus::Error, 'error_message' => $e->getMessage()],
                    );
                    if ($record->wasRecentlyCreated) {
                        $stagingCount++;
                    }
                    $this->log($job, LogLevel::Error, "Ligne #{$rowNumber}: {$e->getMessage()}", [
                        'row_number' => $rowNumber,
                    ]);

                    // ignore → on continue ; stop/rollback → arrêt propre du
                    // parse (rien à rollback à ce stade : aucune écriture Lunar).
                    $halt = $policy !== ErrorPolicy::Ignore;
                }

                $processed++;

                if ($halt) {
                    $stopped = true;
                    $this->persistProgress($job, $processed, $stagingCount, $total);
                    break;
                }

                if ($processed % $chunkEvery === 0) {
                    $this->persistProgress($job, $processed, $stagingCount, $total);
                }
            }

            if ($stopped) {
                $job->update([
                    'status' => JobStatus::Error,
                    'error_message' => "Parse arrêté selon la politique « {$policy->label()} » après {$errors} erreur(s).",
                ]);
                $this->log($job, LogLevel::Error, "Parse interrompu (politique {$policy->value}) : {$errors} erreur(s).", [
                    'last_processed_row' => $processed,
                ]);

                return;
            }

            $job->update([
                'status' => JobStatus::Parsed,
                'processed_rows' => $processed,
                'staging_count' => $stagingCount,
                'last_processed_row' => $processed,
                'parse_completed_at' => now(),
            ]);
            ProgressCache::set($job, $processed, $total);

            $this->log($job, LogLevel::Success, 'Parse terminé', [
                'total_rows' => $total,
                'staging_rows' => $stagingCount,
                'error_rows' => $errors,
            ]);

            ImportJobNotifier::parseCompleted($job->fresh());
        } catch (\Throwable $e) {
            $this->fail($job, $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        ImportJob::query()->where('id', $this->importJobId)->update([
            'status' => JobStatus::Error,
            'error_message' => $e->getMessage(),
        ]);
    }

    private function persistProgress(ImportJob $job, int $processed, int $stagingCount, ?int $total): void
    {
        $job->update([
            'processed_rows' => $processed,
            'staging_count' => $stagingCount,
            'last_processed_row' => $processed,
        ]);
        ProgressCache::set($job, $processed, $total);
    }

    private function fail(ImportJob $job, string $message): void
    {
        DB::transaction(function () use ($job, $message): void {
            $job->update([
                'status' => JobStatus::Error,
                'error_message' => $message,
            ]);
            $this->log($job, LogLevel::Error, $message);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(ImportJob $job, LogLevel $level, string $message, array $context = []): void
    {
        ImportLog::create([
            'import_job_id' => $job->id,
            'level' => $level,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
