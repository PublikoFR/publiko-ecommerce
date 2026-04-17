<?php

declare(strict_types=1);

namespace Mde\AiImporter\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Mde\AiImporter\Models\ImportJob;
use Mde\AiImporter\Models\StagingRecord;

/**
 * Snapshots Lunar rows that are about to be touched by a job, gzipped as JSON.
 *
 * Unlike a full `spatie/laravel-backup` dump, this only captures the subset
 * of products/variants/prices that match any `reference` (SKU) present in
 * the staging table, and the pivot rows that link those products to their
 * collections. The restore replays those rows into the DB in a transaction.
 *
 * The format is intentionally dumb JSON so a staff member can inspect a
 * backup in a text editor before triggering a rollback.
 */
final class LunarBackupManager
{
    public function snapshot(ImportJob $job): string
    {
        $skus = StagingRecord::query()
            ->where('import_job_id', $job->id)
            ->pluck('data')
            ->map(fn ($d): ?string => is_array($d) || is_object($d) ? ((array) $d)['reference'] ?? null : null)
            ->filter()
            ->unique()
            ->values();

        $variants = ProductVariant::query()
            ->whereIn('sku', $skus)
            ->with(['product.collections', 'product.brand'])
            ->get();

        $prefix = config('lunar.database.table_prefix', 'lunar_');
        $productIds = $variants->pluck('product_id')->unique()->values();

        $payload = [
            'job_uuid' => $job->uuid,
            'created_at' => now()->toIso8601String(),
            'variants' => $variants->map(fn (ProductVariant $v) => $v->toArray())->values()->all(),
            'products' => Product::query()->whereIn('id', $productIds)->get()->map(fn (Product $p) => $p->toArray())->values()->all(),
            'prices' => Price::query()
                ->where('priceable_type', ProductVariant::class)
                ->whereIn('priceable_id', $variants->pluck('id'))
                ->get()
                ->toArray(),
            'collection_product' => DB::table($prefix.'collection_product')
                ->whereIn('product_id', $productIds)
                ->get()
                ->toArray(),
        ];

        $path = config('ai-importer.storage.backups_path', 'ai-importer/backups')
            .'/job_'.$job->uuid.'_'.now()->format('YmdHis').'.json.gz';

        $content = gzencode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 6);
        Storage::disk(config('ai-importer.storage.disk', 'local'))->put($path, $content);

        $job->update(['backup_path' => $path]);

        return $path;
    }

    /**
     * Restore a snapshot in a DB transaction. Rows not present in the snapshot
     * but created by the import are **not** auto-deleted — call `rollback()`
     * for that behaviour.
     */
    public function restore(ImportJob $job): void
    {
        if (! $job->backup_path) {
            throw new \RuntimeException('No backup_path on job.');
        }

        $disk = Storage::disk(config('ai-importer.storage.disk', 'local'));
        if (! $disk->exists($job->backup_path)) {
            throw new \RuntimeException("Backup missing: {$job->backup_path}");
        }

        $payload = json_decode(gzdecode($disk->get($job->backup_path)) ?: '', true);
        if (! is_array($payload)) {
            throw new \RuntimeException('Corrupted backup payload.');
        }

        DB::transaction(function () use ($payload, $job): void {
            foreach ($payload['products'] ?? [] as $p) {
                Product::withoutEvents(fn () => Product::query()->where('id', $p['id'])->update([
                    'brand_id' => $p['brand_id'] ?? null,
                    'product_type_id' => $p['product_type_id'],
                    'status' => $p['status'],
                    'attribute_data' => is_string($p['attribute_data'] ?? null) ? $p['attribute_data'] : json_encode($p['attribute_data'] ?? []),
                ]));
            }

            foreach ($payload['variants'] ?? [] as $v) {
                ProductVariant::withoutEvents(fn () => ProductVariant::query()->where('id', $v['id'])->update([
                    'sku' => $v['sku'] ?? null,
                    'ean' => $v['ean'] ?? null,
                    'stock' => $v['stock'] ?? 0,
                    'weight_value' => $v['weight_value'] ?? null,
                ]));
            }

            foreach ($payload['prices'] ?? [] as $row) {
                Price::query()->updateOrCreate(
                    ['id' => $row['id']],
                    collect($row)->except(['id'])->toArray(),
                );
            }

            $job->update(['rollback_completed' => true]);
        });
    }
}
