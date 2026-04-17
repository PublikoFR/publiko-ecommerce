<?php

declare(strict_types=1);

namespace Mde\AiImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mde\AiImporter\Actions\ExecutionContext;
use Mde\AiImporter\Enums\JobStatus;
use Mde\AiImporter\Enums\LogLevel;
use Mde\AiImporter\Enums\StagingStatus;
use Mde\AiImporter\Models\ImportJob;
use Mde\AiImporter\Models\ImportLog;
use Mde\AiImporter\Models\StagingRecord;
use Mde\AiImporter\Services\ActionPipeline;
use Mde\AiImporter\Services\ProgressCache;
use Mde\AiImporter\Services\SpreadsheetParser;

/**
 * Parses the uploaded spreadsheet and populates `mde_ai_importer_staging`.
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
            $parser->load($absolutePath, $config);

            $primary = $parser->primarySheetName();
            $total = $parser->countRows($primary);
            $job->update(['total_rows' => $total]);

            $joinKey = $parser->joinKeyName();
            $mapping = (array) ($config['mapping'] ?? []);
            $chunkEvery = (int) config('ai-importer.defaults.checkpoint_every', 100);
            $rowLimit = $job->row_limit;
            $startAfter = (int) ($job->last_processed_row ?? 0);
            $processed = $startAfter;
            $stagingCount = (int) $job->staging_count;

            foreach ($parser->iterateRows($primary, $startAfter) as $rowNumber => $row) {
                if ($rowLimit !== null && $processed >= $rowLimit) {
                    break;
                }

                $sheets = $joinKey !== null
                    ? $parser->secondarySheetsFor($row[$joinKey] ?? '')
                    : [];

                $ctx = new ExecutionContext(
                    job: $job,
                    row: $row,
                    sheets: $sheets,
                    rowNumber: $rowNumber,
                );

                $mapped = [];
                foreach ($mapping as $outputKey => $columnConfig) {
                    $srcCol = $columnConfig['col'] ?? null;
                    $initial = $srcCol !== null ? ($row[$srcCol] ?? null) : null;
                    $value = $pipeline->run($initial, (array) $columnConfig, $ctx);
                    $mapped[$outputKey] = $value;
                    $ctx->setOutput((string) $outputKey, $value);
                }

                StagingRecord::create([
                    'import_job_id' => $job->id,
                    'row_number' => $rowNumber,
                    'data' => $mapped,
                    'status' => StagingStatus::Pending,
                ]);

                $processed++;
                $stagingCount++;

                if ($processed % $chunkEvery === 0) {
                    $job->update([
                        'processed_rows' => $processed,
                        'staging_count' => $stagingCount,
                        'last_processed_row' => $rowNumber,
                    ]);
                    ProgressCache::set($job, $processed, $total);
                }
            }

            $job->update([
                'status' => JobStatus::Parsed,
                'processed_rows' => $processed,
                'staging_count' => $stagingCount,
                'last_processed_row' => $startAfter + $processed,
                'parse_completed_at' => now(),
            ]);
            ProgressCache::set($job, $processed, $total);

            $this->log($job, LogLevel::Success, 'Parse terminé', [
                'total_rows' => $total,
                'staging_rows' => $stagingCount,
            ]);
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
