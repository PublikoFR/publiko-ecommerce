<?php

declare(strict_types=1);

namespace Mde\AiImporter\Services;

use Illuminate\Support\Facades\Cache;
use Mde\AiImporter\Models\ImportJob;

/**
 * Lightweight progress tracker keyed by job UUID.
 *
 * Read by Livewire polling on the job detail page; written by Jobs as they
 * process rows. Avoids hammering the DB with `SELECT processed_rows` every
 * 2 seconds on dozens of concurrent imports.
 */
final class ProgressCache
{
    private const TTL = 900; // 15 min — long enough to outlive job resume

    public static function set(ImportJob $job, int $processed, ?int $total = null): void
    {
        Cache::put(self::key($job), [
            'processed' => $processed,
            'total' => $total ?? $job->total_rows,
            'percentage' => $total && $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 0,
            'updated_at' => now()->toIso8601String(),
        ], self::TTL);
    }

    /**
     * @return array{processed:int,total:?int,percentage:int,updated_at:string}|null
     */
    public static function get(ImportJob $job): ?array
    {
        return Cache::get(self::key($job));
    }

    public static function forget(ImportJob $job): void
    {
        Cache::forget(self::key($job));
    }

    private static function key(ImportJob $job): string
    {
        return 'ai-importer:job:'.$job->uuid.':progress';
    }
}
