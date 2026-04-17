<?php

declare(strict_types=1);

namespace Mde\AiImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mde\AiImporter\Models\ImportJob;

/**
 * Reads the uploaded spreadsheet in chunks, applies the action pipeline to each
 * row, and writes the result to `mde_ai_importer_staging`.
 *
 * Phase 1: skeleton only. Phase 3 fills in SpreadsheetParser + batched execution.
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

    public function handle(): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);

        // TODO phase 3:
        //  - boot SpreadsheetParser with $job->input_file_path + $job->config
        //  - iterate primary sheet in chunks of $job->chunk_size
        //  - build ExecutionContext per row + feed ActionPipeline for each mapped column
        //  - bulk insert StagingRecord rows
        //  - checkpoint: update $job->processed_rows, $job->last_processed_row
        //  - resume: start from $job->last_processed_row if set
        //  - handle JobStatus transitions (Parsing → Parsed | Error)

        $this->queue()->pushOn($this->queue, $this); // placeholder no-op
    }

    public function failed(\Throwable $e): void
    {
        ImportJob::query()->where('id', $this->importJobId)->update([
            'status' => 'error',
            'error_message' => $e->getMessage(),
        ]);
    }
}
