<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Brand;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\RowFilter;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Enums\UpdateMode;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\CatalogFeatures\Facades\Features;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\ProductVideos\Services\ProductVideoManager;

/**
 * Writes a single `StagingRecord` into the Lunar data model.
 *
 * Contract — the keys the writer understands in `StagingRecord::data`:
 *
 *  - `reference`            (required) SKU, resolves an existing variant.
 *  - `name`                 (required on create) TranslatedText[default_lang].
 *  - `description` / `description_short` / `meta_title` / `meta_description` /
 *    `meta_keywords`        attribute_data, TranslatedText.
 *  - `url_key`              attribute_data.url (Text).
 *  - `ean`                  ProductVariant.ean.
 *  - `stock`                int, ProductVariant.stock.
 *  - `price_cents`          int cents, base Price row.
 *  - `compare_price_cents`  int cents, optional compare-at price.
 *  - `weight_value`         float kg.
 *  - `length_value` / `width_value` / `height_value`  floats in cm.
 *  - `brand_name`           Brand::firstOrCreate(['name' => ...]) → Product.brand_id.
 *  - `product_type_handle`  lookup ProductType by handle, fallback to first.
 *  - `tax_class_handle`     lookup TaxClass by handle, fallback to first.
 *  - `collections`          array|CSV of collection IDs or handles — syncWithoutDetaching.
 *  - `features`             hash `{family_handle => [value_handle, ...]}` — routed through
 *                           notre package `catalog-features` (Features::syncByHandles), NOT Lunar's
 *                           native Attribute/attribute_data system.
 *  - `images`               array|CSV of remote URLs — downloaded into Spatie MediaLibrary
 *                           collection `config('lunar.media.collection')`. Idempotent via
 *                           `custom_properties.source_url`. First URL flagged `primary=true`
 *                           (becomes thumbnail).
 *  - `videos`               array|CSV of YouTube/Vimeo/Dailymotion/MP4 URLs — routed through
 *                           `pko/product-videos` (ProductVideoManager::sync). Idempotent : URL
 *                           déjà attachée au produit = skip, URL non reconnue = counted as error.
 *
 * Anything the writer doesn't recognise is ignored.
 *
 * Unresolved handles (`collections` or `features`) — handles that don't match
 * an existing Collection / FeatureFamily / FeatureValue — are NOT a hard error.
 * The record is still written successfully ; an `ImportLog` warning is recorded
 * with the list of skipped handles for diagnostic. Common with LLM-driven imports
 * where the model can hallucinate handles outside the allowed taxonomy.
 *
 * Legacy aliases (PrestaShop FAB-DIS compat — auto-normalised on entry, canonical key wins):
 *   - `ean13` → `ean`, `quantity` → `stock`, `manufacturer` → `brand_name`,
 *     `link_rewrite` → `url_key`, `depth` → `length_value`, `width` → `width_value`,
 *     `height` → `height_value`, `weight` → `weight_value`, `image` → `images`,
 *     `category` → `collections`.
 *   - `price_tex` (euros, float) → `price_cents` (int, ×100 rounded).
 *
 * Resolvers cache look-ups per instance — build one writer per job, not per row.
 */
final class LunarProductWriter
{
    /** @var array<string, int> */
    private array $brandCache = [];

    /** @var array<string, int> */
    private array $collectionHandleCache = [];

    /** @var array<string, int> */
    private array $productTypeCache = [];

    /** @var array<string, int> */
    private array $taxClassCache = [];

    private ?int $defaultProductTypeId = null;

    private ?int $defaultTaxClassId = null;

    private ?int $defaultCurrencyId = null;

    private ?string $defaultLanguage = null;

    private UpdateMode $updateMode = UpdateMode::All;

    private RowFilter $rowFilter = RowFilter::All;

    private string $joinColumn = 'reference';

    /** @var array<int, string> Sous-ensemble de clés réellement écrites (vide = tout). */
    private array $columnsToImport = [];

    private readonly ProductImagePipeline $imagePipeline;

    /** ID du job courant — 0 = pas de logging granulaire. */
    private int $jobId = 0;

    /** Numéro de ligne en cours de traitement (renseigné à chaque write()). */
    private int $currentRowNumber = 0;

    /** @var array<int, array<string, mixed>> Logs en attente de flush batch. */
    private array $pendingLogs = [];

    public function __construct(?ProductImagePipeline $imagePipeline = null)
    {
        $this->imagePipeline = $imagePipeline ?? new ProductImagePipeline;
    }

    /**
     * Active le logging granulaire ligne par ligne pour ce writer.
     * À appeler une fois par job, avant la boucle d'écriture.
     */
    public function setJobId(int $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    /**
     * Applique les options de job (`pko_ai_importer_jobs.options`) au writer.
     * Idempotent et fluide : appeler une fois par job avant la boucle d'écriture.
     *
     * Clés reconnues : `update_mode`, `row_filter`, `join_column`, `columns_to_import`.
     *
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>|null  $options
     */
    public function configure(array|\ArrayAccess|null $options): self
    {
        $options = $options instanceof \ArrayObject ? $options->getArrayCopy() : (array) ($options ?? []);

        if (isset($options['update_mode'])) {
            $this->updateMode = $options['update_mode'] instanceof UpdateMode
                ? $options['update_mode']
                : (UpdateMode::tryFrom((string) $options['update_mode']) ?? UpdateMode::All);
        }

        if (isset($options['row_filter'])) {
            $this->rowFilter = $options['row_filter'] instanceof RowFilter
                ? $options['row_filter']
                : (RowFilter::tryFrom((string) $options['row_filter']) ?? RowFilter::All);
        }

        if (! empty($options['join_column'])) {
            $this->joinColumn = (string) $options['join_column'];
        }

        if (! empty($options['columns_to_import']) && is_array($options['columns_to_import'])) {
            $this->columnsToImport = array_values(array_map('strval', $options['columns_to_import']));
        }

        return $this;
    }

    public function write(StagingRecord $record): void
    {
        $this->currentRowNumber = $record->row_number ?? 0;

        try {
            $this->doWrite($record);
        } finally {
            $this->flushPendingLogs();
        }
    }

    private function doWrite(StagingRecord $record): void
    {
        $data = $record->data instanceof \ArrayObject
            ? $record->data->getArrayCopy()
            : (array) $record->data;

        $data = self::normalizeLegacyKeys($data);
        $data = $this->filterImportColumns($data);

        $sku = trim((string) ($data['reference'] ?? ''));
        if ($sku === '') {
            $this->markError($record, 'reference manquante');

            return;
        }

        $this->addLog(LogLevel::Debug, "Début importRow ligne {$this->currentRowNumber}");
        $this->addLog(LogLevel::Debug, "Recherche produit existant (joinColumn: {$this->joinColumn})");
        $this->addLog(LogLevel::Debug, "valeur clé = {$sku}");

        $variant = $this->findExistingVariant($data, $sku);
        $product = $variant?->product;
        $exists = $product !== null;

        if ($exists) {
            $this->addLog(LogLevel::Debug, "Produit trouvé id={$product->id}");
        } else {
            $this->addLog(LogLevel::Debug, 'Création produit');
        }

        // row_filter — n'écrire que les produits absents / existants selon l'option.
        if (! $this->rowFilter->allows($exists)) {
            $record->update([
                'status' => StagingStatus::Skipped,
                'lunar_product_id' => $product?->id,
                'error_message' => null,
            ]);

            return;
        }

        $unresolved = ['collections' => [], 'features' => []];

        if (! $exists) {
            // Création : toujours intégrale (update_mode ne s'applique qu'aux MAJ).
            if (! isset($data['name']) || $data['name'] === '') {
                $this->markError($record, 'name requis à la création');

                return;
            }

            $product = Product::query()->create([
                'product_type_id' => $this->resolveProductTypeId($data),
                'status' => 'published',
                'brand_id' => $this->resolveBrand($data),
                'attribute_data' => $this->buildAttributeData($data),
            ]);

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'tax_class_id' => $this->resolveTaxClassId($data),
                'sku' => $sku,
                'ean' => $data['ean'] ?? null,
                'stock' => (int) ($data['stock'] ?? 0),
                'unit_quantity' => 1,
                'min_quantity' => 1,
                'quantity_increment' => 1,
                'shippable' => true,
                'purchasable' => 'always',
                ...$this->dimensions($data),
            ]);

            $this->applyPrice($variant, $data);
            $unresolved = $this->applyRelations($product, $data);
            $wasCreate = true;
        } else {
            // Mise à jour : champs écrits selon update_mode.
            $wasCreate = false;
            $this->addLog(LogLevel::Debug, "Mode update: {$this->updateMode->value}");

            if ($this->updateMode->writesFullRecord()) {
                $product->update(array_filter([
                    'brand_id' => $this->resolveBrand($data),
                    'attribute_data' => $this->buildAttributeData($data, $product->attribute_data),
                ], static fn ($v) => $v !== null));

                $variant->update(array_filter([
                    'ean' => $data['ean'] ?? null,
                    'stock' => isset($data['stock']) ? (int) $data['stock'] : null,
                    ...$this->dimensions($data),
                ], static fn ($v) => $v !== null));

                $this->applyPrice($variant, $data);
                $unresolved = $this->applyRelations($product, $data);
            } else {
                if ($this->updateMode->writesPrice()) {
                    $this->applyPrice($variant, $data);
                }
                if ($this->updateMode->writesStock() && isset($data['stock'])) {
                    $variant->update(['stock' => (int) $data['stock']]);
                }
            }
        }

        $record->update([
            'status' => $wasCreate ? StagingStatus::Created : StagingStatus::Updated,
            'lunar_product_id' => $product->id,
            'imported_at' => now(),
            'error_message' => null,
        ]);

        $this->logUnresolvedHandles($record, $sku, $unresolved);
    }

    /**
     * Restreint les données à `columns_to_import` quand l'option est définie.
     * Les clés essentielles à l'identification (reference, name, colonne de
     * jointure) sont toujours conservées, sinon le produit deviendrait
     * impossible à créer ou résoudre.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterImportColumns(array $data): array
    {
        if ($this->columnsToImport === []) {
            return $data;
        }

        $essential = ['reference', 'name', $this->joinColumn];
        $keep = array_flip(array_unique([...$this->columnsToImport, ...$essential]));

        return array_intersect_key($data, $keep);
    }

    /**
     * Résout le variant existant selon la colonne de jointure (`join_column`).
     * Lunar n'a pas de champ « référence fournisseur » natif : mappez votre réf
     * fournisseur sur `reference` (→ SKU) dans le mapping de config. Jointures
     * supportées : `reference`/`sku` (défaut) et `ean`/`ean13`.
     *
     * @param  array<string, mixed>  $data
     */
    private function findExistingVariant(array $data, string $sku): ?ProductVariant
    {
        $column = match ($this->joinColumn) {
            'ean', 'ean13' => 'ean',
            default => 'sku',
        };

        $value = $column === 'ean' ? trim((string) ($data['ean'] ?? '')) : $sku;
        if ($value === '') {
            return null;
        }

        return ProductVariant::query()->where($column, $value)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyPrice(ProductVariant $variant, array $data): void
    {
        if (! isset($data['price_cents'])) {
            return;
        }

        $this->upsertPrice(
            $variant,
            (int) $data['price_cents'],
            isset($data['compare_price_cents']) ? (int) $data['compare_price_cents'] : null,
        );
    }

    /**
     * Synchronise collections / features / images / vidéos d'un produit.
     *
     * @param  array<string, mixed>  $data
     * @return array{collections: array<int, string>, features: array<int, string>}
     */
    private function applyRelations(Product $product, array $data): array
    {
        $unresolved = ['collections' => [], 'features' => []];

        if (! empty($data['collections'])) {
            $resolution = $this->resolveCollectionIds($data['collections']);
            if ($resolution['ids'] !== []) {
                $product->collections()->syncWithoutDetaching($resolution['ids']);
            }
            $unresolved['collections'] = $resolution['unresolved'];
        }

        if (! empty($data['features']) && is_array($data['features']) && class_exists(Features::class)) {
            $unresolved['features'] = $this->findUnresolvedFeatures($data['features']);
            Features::syncByHandles($product, $data['features']);
        }

        if (! empty($data['images'])) {
            $this->imagePipeline->syncImages($product, $data['images'], function (string $url, string $status, string $error = ''): void {
                if ($status === 'added') {
                    $this->addLog(LogLevel::Debug, "Image téléchargée: {$url}");
                } elseif ($status === 'skipped') {
                    $this->addLog(LogLevel::Debug, "Image déjà existante, ignorée: {$url}");
                } else {
                    $this->addLog(LogLevel::Warning, "Erreur image: {$url} — {$error}");
                }
            });
        }

        if (! empty($data['videos'])) {
            $videoUrls = is_string($data['videos'])
                ? array_map('trim', explode(',', $data['videos']))
                : (array) $data['videos'];
            app(ProductVideoManager::class)->sync($product, $videoUrls);
        }

        return $unresolved;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildAttributeData(array $data, Collection|\ArrayAccess|null $existing = null): ?Collection
    {
        $lang = $this->defaultLanguage();

        $existingArr = $existing instanceof Collection
            ? $existing->all()
            : ($existing instanceof \ArrayAccess ? iterator_to_array($existing) : []);

        $fields = collect($existingArr);

        $translateIf = function (string $key, Collection $fields) use ($data, $lang): Collection {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                return $fields;
            }
            /** @var TranslatedText|null $current */
            $current = $fields->get($key);
            $values = $current?->getValue() ?? [];
            $values[$lang] = (string) $data[$key];

            return $fields->put($key, new TranslatedText(collect($values)));
        };

        foreach (['name', 'description', 'description_short', 'meta_title', 'meta_description', 'meta_keywords'] as $k) {
            $fields = $translateIf($k, $fields);
        }

        if (isset($data['url_key']) && $data['url_key'] !== '') {
            $fields = $fields->put('url', new Text((string) $data['url_key']));
        }

        return $fields->isEmpty() ? null : $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dimensions(array $data): array
    {
        $out = [];
        if (isset($data['weight_value'])) {
            $out['weight_value'] = (float) $data['weight_value'];
            $out['weight_unit'] = 'kg';
        }
        foreach (['length', 'width', 'height'] as $axis) {
            if (isset($data[$axis.'_value'])) {
                $out[$axis.'_value'] = (float) $data[$axis.'_value'];
                $out[$axis.'_unit'] = 'cm';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBrand(array $data): ?int
    {
        $name = isset($data['brand_name']) ? trim((string) $data['brand_name']) : '';
        if ($name === '') {
            return null;
        }
        if (isset($this->brandCache[$name])) {
            return $this->brandCache[$name];
        }
        $brand = Brand::query()->firstOrCreate(['name' => $name]);

        return $this->brandCache[$name] = (int) $brand->id;
    }

    /**
     * Resolve a list of collection IDs or handles to int IDs. Unresolved handles
     * (no Collection with that handle in DB) are returned separately for warning
     * surfacing — common when an LLM emits a handle that doesn't exist yet.
     *
     * @return array{ids: array<int, int>, unresolved: array<int, string>}
     */
    private function resolveCollectionIds(mixed $value): array
    {
        $raw = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
        $ids = [];
        $unresolved = [];

        foreach ($raw as $token) {
            if (is_numeric($token)) {
                $ids[] = (int) $token;

                continue;
            }
            $handle = (string) $token;
            if ($handle === '') {
                continue;
            }
            if (isset($this->collectionHandleCache[$handle])) {
                $ids[] = $this->collectionHandleCache[$handle];

                continue;
            }
            $collection = \Lunar\Models\Collection::query()->where('handle', $handle)->first();
            if ($collection) {
                $ids[] = $this->collectionHandleCache[$handle] = (int) $collection->id;
            } else {
                $unresolved[] = $handle;
            }
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'unresolved' => array_values(array_unique($unresolved)),
        ];
    }

    /**
     * Identify family or value handles from a `features` hash that don't match
     * any FeatureFamily / FeatureValue in DB. Returned as a flat list like
     * `["family:unknown_family", "value:unknown_family.unknown_value"]` for
     * easy filter/sort in logs.
     *
     * @param  array<string, array<int, string>>  $features
     * @return array<int, string>
     */
    private function findUnresolvedFeatures(array $features): array
    {
        if ($features === [] || ! class_exists(FeatureFamily::class)) {
            return [];
        }

        $families = FeatureFamily::query()
            ->whereIn('handle', array_keys($features))
            ->with('values:id,feature_family_id,handle')
            ->get()
            ->keyBy('handle');

        $unresolved = [];

        foreach ($features as $familyHandle => $valueHandles) {
            $family = $families->get($familyHandle);
            if ($family === null) {
                $unresolved[] = "family:{$familyHandle}";

                continue;
            }
            $known = $family->values->pluck('handle')->all();
            foreach ((array) $valueHandles as $value) {
                $value = (string) $value;
                if ($value !== '' && ! in_array($value, $known, true)) {
                    $unresolved[] = "value:{$familyHandle}.{$value}";
                }
            }
        }

        return $unresolved;
    }

    /**
     * @param  array{collections: array<int, string>, features: array<int, string>}  $unresolved
     */
    private function logUnresolvedHandles(StagingRecord $record, string $sku, array $unresolved): void
    {
        if ($unresolved['collections'] === [] && $unresolved['features'] === []) {
            return;
        }

        $parts = [];
        if ($unresolved['collections'] !== []) {
            $parts[] = count($unresolved['collections']).' collection(s)';
        }
        if ($unresolved['features'] !== []) {
            $parts[] = count($unresolved['features']).' feature(s)';
        }

        $message = "[{$sku}] handle(s) introuvable(s) — ".implode(', ', $parts).' ignoré(s).';
        $context = array_filter($unresolved, static fn (array $v) => $v !== []);

        if ($this->jobId !== 0) {
            $this->addLog(LogLevel::Warning, $message, $context);
        } else {
            ImportLog::query()->create([
                'import_job_id' => $record->import_job_id,
                'row_number' => $record->row_number,
                'level' => LogLevel::Warning,
                'message' => $message,
                'context' => $context,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function addLog(LogLevel $level, string $message, ?array $context = null): void
    {
        if ($this->jobId === 0) {
            return;
        }

        $this->pendingLogs[] = [
            'import_job_id' => $this->jobId,
            'row_number' => $this->currentRowNumber ?: null,
            'level' => $level->value,
            'message' => $message,
            'context' => $context !== null ? json_encode($context) : null,
        ];
    }

    private function flushPendingLogs(): void
    {
        if ($this->pendingLogs === []) {
            return;
        }

        DB::table('pko_ai_importer_logs')->insert($this->pendingLogs);
        $this->pendingLogs = [];
    }

    private function upsertPrice(ProductVariant $variant, int $priceCents, ?int $comparePriceCents): void
    {
        Price::query()->updateOrCreate(
            [
                'priceable_type' => ProductVariant::class,
                'priceable_id' => $variant->id,
                'currency_id' => $this->defaultCurrencyId(),
                'customer_group_id' => null,
                'min_quantity' => 1,
            ],
            [
                'price' => $priceCents,
                'compare_price' => $comparePriceCents,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProductTypeId(array $data): int
    {
        $handle = isset($data['product_type_handle']) ? (string) $data['product_type_handle'] : '';
        if ($handle === '') {
            return $this->defaultProductTypeId();
        }
        if (isset($this->productTypeCache[$handle])) {
            return $this->productTypeCache[$handle];
        }
        $id = (int) (ProductType::query()->where('handle', $handle)->value('id') ?? $this->defaultProductTypeId());

        return $this->productTypeCache[$handle] = $id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTaxClassId(array $data): int
    {
        $handle = isset($data['tax_class_handle']) ? (string) $data['tax_class_handle'] : '';
        if ($handle === '') {
            return $this->defaultTaxClassId();
        }
        if (isset($this->taxClassCache[$handle])) {
            return $this->taxClassCache[$handle];
        }
        $id = (int) (TaxClass::query()->where('handle', $handle)->value('id') ?? $this->defaultTaxClassId());

        return $this->taxClassCache[$handle] = $id;
    }

    private function defaultProductTypeId(): int
    {
        return $this->defaultProductTypeId ??= (int) (ProductType::query()->orderBy('id')->value('id') ?? throw new \RuntimeException('No ProductType exists — seed at least one.'));
    }

    private function defaultTaxClassId(): int
    {
        return $this->defaultTaxClassId ??= (int) (TaxClass::query()->orderBy('id')->value('id') ?? throw new \RuntimeException('No TaxClass exists — seed at least one.'));
    }

    private function defaultCurrencyId(): int
    {
        return $this->defaultCurrencyId ??= (int) (Currency::query()->where('default', true)->value('id')
            ?? Currency::query()->orderBy('id')->value('id')
            ?? throw new \RuntimeException('No Currency exists.'));
    }

    private function defaultLanguage(): string
    {
        return $this->defaultLanguage ??= (string) (Language::query()->where('default', true)->value('code')
            ?? Language::query()->orderBy('id')->value('code')
            ?? 'fr');
    }

    private function markError(StagingRecord $record, string $message): void
    {
        $record->update([
            'status' => StagingStatus::Error,
            'error_message' => $message,
        ]);
    }

    /**
     * Tolerance layer for PrestaShop-flavoured config JSON.
     *
     * Maps legacy keys to the canonical writer contract. The canonical key always
     * wins when both are present. Conversions (€→cents, dimension axes) are
     * applied only when the canonical target is empty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeLegacyKeys(array $data): array
    {
        $aliases = [
            'ean13' => 'ean',
            'quantity' => 'stock',
            'manufacturer' => 'brand_name',
            'link_rewrite' => 'url_key',
            'depth' => 'length_value',
            'width' => 'width_value',
            'height' => 'height_value',
            'weight' => 'weight_value',
            'category' => 'collections',
            'image' => 'images',
        ];

        foreach ($aliases as $legacy => $canonical) {
            if (! array_key_exists($legacy, $data)) {
                continue;
            }
            if (! array_key_exists($canonical, $data) || $data[$canonical] === null || $data[$canonical] === '') {
                $data[$canonical] = $data[$legacy];
            }
            unset($data[$legacy]);
        }

        // price_tex (PrestaShop, in euros — float or string) → price_cents (int cents).
        if (array_key_exists('price_tex', $data)) {
            if (! array_key_exists('price_cents', $data) || $data['price_cents'] === null || $data['price_cents'] === '') {
                $euros = is_numeric($data['price_tex']) ? (float) $data['price_tex'] : 0.0;
                $data['price_cents'] = (int) round($euros * 100);
            }
            unset($data['price_tex']);
        }

        return $data;
    }
}
