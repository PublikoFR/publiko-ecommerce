<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\CartLine;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\LunarMediaCore\Models\Mediable;

/**
 * Snapshots Lunar rows that are about to be touched by a job, gzipped as JSON.
 *
 * Unlike a full `spatie/laravel-backup` dump, this only captures the subset
 * of products/variants/prices that match any `reference` (SKU) or `ean`
 * present in the staging table, and the collection pivot rows of those
 * products. The restore replays those rows into the DB in a transaction.
 *
 * Format v2 : rows are captured RAW (`getRawOriginal()`), never through
 * `toArray()` — `toArray()` flattens `attribute_data` (`{"name": "…"}`) and
 * writing that back breaks Lunar's AsAttributeData cast on every product.
 *
 * Restore also removes what the import CREATED (staging status=created) :
 * force-deleted through Lunar models when nothing references the variants
 * (order / cart lines), set to `draft` otherwise.
 *
 * Not restored : tags, catalog features, images, documents and videos ADDED
 * to existing products by the import (the writer only appends them).
 *
 * The format is intentionally dumb JSON so a staff member can inspect a
 * backup in a text editor before triggering a rollback.
 */
final class LunarBackupManager
{
    private const FORMAT_VERSION = 2;

    /** Product columns the writer can change on update. */
    private const PRODUCT_COLUMNS = [
        'brand_id', 'product_type_id', 'status', 'attribute_data', 'pko_supplier_id', 'pko_port_mode',
    ];

    /** Variant columns the writer can change on update. */
    private const VARIANT_COLUMNS = [
        'sku', 'ean', 'mpn', 'stock', 'min_quantity', 'pko_cost_price',
        'weight_value', 'weight_unit', 'length_value', 'length_unit',
        'width_value', 'width_unit', 'height_value', 'height_unit',
    ];

    /** Placeholder budget per `whereIn` (MySQL caps a statement at 65 535). */
    private const CHUNK = 1000;

    public function snapshot(ImportJob $job): string
    {
        [$skus, $eans] = $this->stagingKeys($job);

        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = new EloquentCollection;
        foreach ([['sku', $skus], ['ean', $eans]] as [$column, $values]) {
            foreach (array_chunk($values, self::CHUNK) as $chunk) {
                $variants = $variants->merge(ProductVariant::query()->whereIn($column, $chunk)->get());
            }
        }
        $variants = $variants->unique('id')->values();

        $variantIds = $variants->pluck('id')->all();
        $productIds = $variants->pluck('product_id')->unique()->values()->all();
        $variantMorph = (new ProductVariant)->getMorphClass();
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        $payload = [
            'version' => self::FORMAT_VERSION,
            'job_uuid' => $job->uuid,
            'created_at' => now()->toIso8601String(),
            'variants' => $variants->map(fn (ProductVariant $v): array => $v->getRawOriginal())->all(),
            'products' => $this->chunked($productIds, fn (array $ids) => Product::query()->whereIn('id', $ids)->get())
                ->map(fn (Product $p): array => $p->getRawOriginal())->values()->all(),
            'prices' => $this->chunked($variantIds, fn (array $ids) => Price::query()
                ->where('priceable_type', $variantMorph)
                ->whereIn('priceable_id', $ids)
                ->get())
                ->map(fn (Price $p): array => $p->getRawOriginal())->values()->all(),
            'collection_product' => $this->chunked($productIds, fn (array $ids) => DB::table($prefix.'collection_product')
                ->whereIn('product_id', $ids)
                ->get(['collection_id', 'product_id', 'position']))
                ->map(fn (object $row): array => (array) $row)->values()->all(),
        ];

        $path = config('ai-importer.storage.backups_path', 'ai-importer/backups')
            .'/job_'.$job->uuid.'_'.now()->format('YmdHis').'.json.gz';

        $content = gzencode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 6);
        Storage::disk(config('ai-importer.storage.disk', 'local'))->put($path, $content);

        $job->update(['backup_path' => $path]);

        return $path;
    }

    /**
     * Restore the snapshot and remove what the import created, in one DB
     * transaction. Media files stay in the library (outside the transaction) :
     * only their links to deleted products are removed.
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
            $this->restoreProducts($job, $payload['products'] ?? []);
            $this->restoreVariants($payload['variants'] ?? []);
            $this->restorePrices($payload['variants'] ?? [], $payload['prices'] ?? []);
            $this->restoreCollections($payload['products'] ?? [], $payload['collection_product'] ?? []);
            $this->removeCreatedProducts($job);

            // Les lignes réellement écrites redeviennent importables.
            StagingRecord::query()
                ->where('import_job_id', $job->id)
                ->whereIn('status', [StagingStatus::Updated->value, StagingStatus::Imported->value])
                ->update(['status' => StagingStatus::Pending->value, 'imported_at' => null]);

            $job->update(['rollback_completed' => true]);
        });
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>} [skus, eans]
     */
    private function stagingKeys(ImportJob $job): array
    {
        $skus = [];
        $eans = [];

        StagingRecord::query()
            ->where('import_job_id', $job->id)
            ->select(['id', 'data'])
            ->chunkById(1000, function ($records) use (&$skus, &$eans): void {
                foreach ($records as $record) {
                    $data = $record->data instanceof \ArrayObject ? $record->data->getArrayCopy() : (array) $record->data;
                    // Mêmes alias que le writer (ean13 → ean…).
                    $data = LunarProductWriter::normalizeLegacyKeys($data);

                    $sku = trim((string) ($data['reference'] ?? ''));
                    $ean = trim((string) ($data['ean'] ?? ''));
                    if ($sku !== '') {
                        $skus[$sku] = true;
                    }
                    if ($ean !== '') {
                        $eans[$ean] = true;
                    }
                }
            });

        return [array_map('strval', array_keys($skus)), array_map('strval', array_keys($eans))];
    }

    /**
     * @param  array<int, int|string>  $ids
     * @param  callable(array<int, int|string>): iterable<mixed>  $query
     * @return Collection<int, mixed>
     */
    private function chunked(array $ids, callable $query): Collection
    {
        $out = collect();
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $out = $out->concat($query($chunk));
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function restoreProducts(ImportJob $job, array $rows): void
    {
        foreach ($rows as $row) {
            $values = array_intersect_key($row, array_flip(self::PRODUCT_COLUMNS));

            // Backup v1 : attribute_data aplati par toArray() — le réécrire
            // casserait le produit. On restaure le reste et on le signale.
            if (array_key_exists('attribute_data', $values) && ! is_string($values['attribute_data'])) {
                unset($values['attribute_data']);
                $this->log($job, LogLevel::Warning, "Produit #{$row['id']} : sauvegarde ancien format, attribute_data non restauré.");
            }

            // Builder Eloquent : ni cast ni fillable → JSON brut et colonnes pko_ écrits tels quels.
            if ($values !== []) {
                Product::query()->whereKey($row['id'])->update($values);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function restoreVariants(array $rows): void
    {
        foreach ($rows as $row) {
            $values = array_intersect_key($row, array_flip(self::VARIANT_COLUMNS));
            if ($values !== []) {
                ProductVariant::query()->whereKey($row['id'])->update($values);
            }
        }
    }

    /**
     * Remet les prix sauvegardés et supprime ceux que l'import a ajoutés aux
     * variantes sauvegardées.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<int, array<string, mixed>>  $prices
     */
    private function restorePrices(array $variants, array $prices): void
    {
        $variantMorph = (new ProductVariant)->getMorphClass();

        foreach ($prices as $row) {
            $values = collect($row)->except(['id'])->all();
            if (Price::query()->whereKey($row['id'])->exists()) {
                Price::query()->whereKey($row['id'])->update($values);
            } else {
                Price::query()->insert($row);
            }
        }

        $keep = array_flip(array_column($prices, 'id'));
        foreach (array_chunk(array_column($variants, 'id'), self::CHUNK) as $chunk) {
            Price::query()
                ->where('priceable_type', $variantMorph)
                ->whereIn('priceable_id', $chunk)
                ->get()
                ->reject(fn (Price $price): bool => isset($keep[$price->id]))
                ->each(fn (Price $price) => $price->delete());
        }
    }

    /**
     * Rétablit EXACTEMENT les collections d'avant l'import pour chaque produit
     * sauvegardé (y compris un produit qui n'en avait aucune).
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, array<string, mixed>>  $pivot
     */
    private function restoreCollections(array $products, array $pivot): void
    {
        $byProduct = [];
        foreach ($pivot as $row) {
            $byProduct[(int) $row['product_id']][(int) $row['collection_id']] = ['position' => (int) ($row['position'] ?? 0)];
        }

        foreach ($products as $row) {
            $product = Product::query()->find($row['id']);
            $product?->collections()->sync($byProduct[(int) $row['id']] ?? []);
        }
    }

    /**
     * Produits créés par l'import : supprimés définitivement via les modèles
     * Lunar (l'observer purge variantes, prix, URLs, collections, tags,
     * canaux ; les FK en cascade purgent documents, vidéos, caractéristiques),
     * sauf si une ligne de commande ou de panier pointe sur une variante :
     * le produit passe alors en brouillon pour ne pas casser l'historique.
     */
    private function removeCreatedProducts(ImportJob $job): void
    {
        $variantMorph = (new ProductVariant)->getMorphClass();

        $records = StagingRecord::query()
            ->where('import_job_id', $job->id)
            ->where('status', StagingStatus::Created->value)
            ->get();

        foreach ($records as $record) {
            $product = $record->lunar_product_id
                ? Product::withTrashed()->find($record->lunar_product_id)
                : null;

            if ($product === null) {
                $record->update(['status' => StagingStatus::Pending, 'lunar_product_id' => null, 'imported_at' => null]);

                continue;
            }

            $variantIds = $product->variants()->withTrashed()->pluck('id');
            $referenced = OrderLine::query()->where('purchasable_type', $variantMorph)->whereIn('purchasable_id', $variantIds)->exists()
                || CartLine::query()->where('purchasable_type', $variantMorph)->whereIn('purchasable_id', $variantIds)->exists();

            if ($referenced) {
                Product::query()->whereKey($product->id)->update(['status' => 'draft']);
                $message = "Produit #{$product->id} créé par l'import mais déjà commandé ou en panier : passé en brouillon, pas supprimé.";
                $record->update([
                    'status' => StagingStatus::Pending,
                    'imported_at' => null,
                    'error_message' => $message,
                ]);
                $this->log($job, LogLevel::Warning, $message, $record->row_number);

                continue;
            }

            Mediable::query()
                ->where('mediable_type', $product::class)
                ->where('mediable_id', $product->id)
                ->delete();
            $product->forceDelete();

            $record->update(['status' => StagingStatus::Pending, 'lunar_product_id' => null, 'imported_at' => null]);
        }
    }

    private function log(ImportJob $job, LogLevel $level, string $message, ?int $rowNumber = null): void
    {
        ImportLog::query()->create([
            'import_job_id' => $job->id,
            'row_number' => $rowNumber,
            'level' => $level,
            'message' => $message,
            'created_at' => now(),
        ]);
    }
}
