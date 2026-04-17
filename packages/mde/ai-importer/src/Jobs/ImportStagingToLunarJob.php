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
 * Reads validated staging rows and writes them to Lunar core models
 * (Product, ProductVariant, Price, Collection, Brand, etc.) + MDE features.
 *
 * Phase 1: skeleton only. Phase 4 fills in LunarProductWriter + backup manager.
 */
class ImportStagingToLunarJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1; // don't retry — rely on resume via last_processed_row

    public function __construct(public readonly int $importJobId) {}

    public function handle(): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);

        // TODO phase 4:
        //  - LunarBackupManager::snapshot($job) → stores backup_path on the job
        //  - iterate StagingRecord (status in [pending, validated]) in chunks
        //  - LunarProductWriter::upsert($record) → Product + ProductVariant + Price + Collections + Brand
        //  - Features::syncByHandles($product, $featureMap) for MDE catalog-features
        //  - Spatie MediaLibrary addMediaFromUrl for product images
        //  - checkpoint every config('ai-importer.defaults.checkpoint_every') rows
        //  - error_policy handling (ignore | stop | rollback)
    }

    public function failed(\Throwable $e): void
    {
        ImportJob::query()->where('id', $this->importJobId)->update([
            'import_status' => 'error',
            'error_message' => $e->getMessage(),
        ]);
    }
}
