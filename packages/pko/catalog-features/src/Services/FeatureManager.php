<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Events\FeatureValueAttached;
use Pko\CatalogFeatures\Events\FeatureValueDetached;
use Pko\CatalogFeatures\Events\ProductFeaturesSynced;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\CatalogFeatures\Models\FeatureValue;

/**
 * Public API surface for catalog features.
 *
 * Other modules (FAB-DIS importer, Blade front, admin extensions…) must go
 * through this manager rather than poking the pivot directly so the domain
 * events fire on every write.
 */
class FeatureManager
{
    public function attach(Product $product, FeatureValue|int $value): void
    {
        $value = $value instanceof FeatureValue ? $value : FeatureValue::findOrFail($value);

        $product->featureValues()->syncWithoutDetaching([$value->id]);

        FeatureValueAttached::dispatch($product, $value);
    }

    public function detach(Product $product, FeatureValue|int $value): void
    {
        $value = $value instanceof FeatureValue ? $value : FeatureValue::findOrFail($value);

        $product->featureValues()->detach($value->id);

        FeatureValueDetached::dispatch($product, $value);
    }

    /**
     * Sync the complete list of feature value ids for this product.
     *
     * @param  array<int>  $valueIds
     * @return array{attached: array<int>, detached: array<int>}
     */
    public function sync(Product $product, array $valueIds): array
    {
        $valueIds = array_values(array_unique(array_map('intval', $valueIds)));

        $result = $product->featureValues()->sync($valueIds);

        $attached = array_values(array_map('intval', $result['attached'] ?? []));
        $detached = array_values(array_map('intval', $result['detached'] ?? []));

        ProductFeaturesSynced::dispatch($product, $attached, $detached);

        return ['attached' => $attached, 'detached' => $detached];
    }

    /**
     * Sync features using human handles instead of ids. Intended for imports.
     *
     * Only families explicitly listed are touched — existing attachments on
     * other families are preserved.
     *
     * @param  array<string, array<string>|string>  $familyHandleToValueHandles
     * @return array{attached: array<int>, detached: array<int>}
     */
    public function syncByHandles(Product $product, array $familyHandleToValueHandles): array
    {
        if ($familyHandleToValueHandles === []) {
            return ['attached' => [], 'detached' => []];
        }

        $families = FeatureFamily::query()
            ->whereIn('handle', array_keys($familyHandleToValueHandles))
            ->with('values')
            ->get()
            ->keyBy('handle');

        $targetValueIds = [];

        foreach ($familyHandleToValueHandles as $familyHandle => $valueHandles) {
            $family = $families->get($familyHandle);
            if ($family === null) {
                continue;
            }

            $wanted = (array) $valueHandles;
            if ($wanted === []) {
                continue;
            }

            $ids = $family->values
                ->whereIn('handle', $wanted)
                ->pluck('id')
                ->all();

            array_push($targetValueIds, ...$ids);
        }

        // Preserve attachments from families NOT listed in the input.
        $preserved = $product->featureValues()
            ->whereNotIn('pko_feature_values.feature_family_id', $families->pluck('id'))
            ->pluck('pko_feature_values.id')
            ->all();

        $final = array_values(array_unique([...$preserved, ...$targetValueIds]));

        return $this->sync($product, $final);
    }

    /**
     * Return the feature values currently attached to a product, grouped by family.
     *
     * @return Collection<int, EloquentCollection<int, FeatureValue>> keyed by feature_family_id
     */
    public function for(Product $product): Collection
    {
        return $product->featureValues()
            ->with('family')
            ->get()
            ->groupBy('feature_family_id');
    }

    /**
     * Families applicable to this product.
     *
     * A family is applicable if it is global (no collection bindings) OR if it
     * is bound to at least one of the product's own collections.
     *
     * @return EloquentCollection<int, FeatureFamily>
     */
    public function familiesFor(Product $product): EloquentCollection
    {
        $collectionIds = $product->collections()->pluck('lunar_collections.id')->all();

        return FeatureFamily::query()
            ->ordered()
            ->with('values')
            ->where(function (Builder $query) use ($collectionIds): void {
                $query->whereDoesntHave('collections');

                if ($collectionIds !== []) {
                    $query->orWhereHas(
                        'collections',
                        fn (Builder $c) => $c->whereIn('collection_id', $collectionIds),
                    );
                }
            })
            ->get();
    }

    /**
     * Return a Product query filtered by ALL supplied value ids.
     *
     * Uses a COUNT(DISTINCT) trick so products that match every requested
     * value are returned. Caller is free to chain paginate/filter on top.
     *
     * @param  array<int>  $valueIds
     */
    public function productsWith(array $valueIds): Builder
    {
        $valueIds = array_values(array_unique(array_map('intval', $valueIds)));

        if ($valueIds === []) {
            return Product::query();
        }

        $expected = count($valueIds);

        return Product::query()
            ->whereIn('id', function ($sub) use ($valueIds, $expected): void {
                $sub->from('pko_feature_value_product')
                    ->select('product_id')
                    ->whereIn('feature_value_id', $valueIds)
                    ->groupBy('product_id')
                    ->havingRaw('COUNT(DISTINCT feature_value_id) = ?', [$expected]);
            });
    }

    /**
     * Face-counts per family/value pour un query de base arbitraire, avec option
     * d'exclure les filtres d'une famille donnée (pattern PrestaShop : les options
     * sibling dans la famille sélectionnée restent visibles au lieu de tomber à 0).
     *
     * @param  Builder<Product>  $baseQuery  Query de base (ex: produits d'une collection,
     *                                       d'une marque, d'une recherche)
     * @param  array<int, array<int, bool|int>>  $selectedByFamily  [family_id => [value_id => true|1]]
     * @param  int|null  $excludeFamilyId  Si fourni, les filtres de cette famille ne sont PAS appliqués
     *                                     au recount (permet de garder les siblings visibles)
     * @return array<int, int> [value_id => count] — limité aux valeurs de excludeFamilyId si fourni,
     *                         sinon toutes les valeurs visibles dans le subset
     */
    public function countsForContext(Builder $baseQuery, array $selectedByFamily, ?int $excludeFamilyId = null): array
    {
        // Clone pour ne pas polluer la query passée en paramètre
        $query = (clone $baseQuery)->toBase();

        // Appliquer tous les filtres feature SAUF ceux de la famille excluée
        foreach ($selectedByFamily as $familyId => $values) {
            if ((int) $familyId === $excludeFamilyId) {
                continue;
            }
            $valueIds = array_values(array_filter(array_map('intval', array_keys(array_filter($values)))));
            if ($valueIds === []) {
                continue;
            }
            $expected = count($valueIds);
            $query->whereIn('lunar_products.id', function ($sub) use ($valueIds, $expected): void {
                $sub->from('pko_feature_value_product')
                    ->select('product_id')
                    ->whereIn('feature_value_id', $valueIds)
                    ->groupBy('product_id')
                    ->havingRaw('COUNT(DISTINCT feature_value_id) = ?', [$expected]);
            });
        }

        // Comptage par value_id via inner join sur le pivot
        $rows = $query
            ->join('pko_feature_value_product as fvp_c', 'fvp_c.product_id', '=', 'lunar_products.id')
            ->join('pko_feature_values as fv_c', 'fv_c.id', '=', 'fvp_c.feature_value_id')
            ->when($excludeFamilyId !== null, fn ($q) => $q->where('fv_c.feature_family_id', $excludeFamilyId))
            ->groupBy('fv_c.id')
            ->selectRaw('fv_c.id as value_id, COUNT(DISTINCT lunar_products.id) as cnt')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->value_id] = (int) $row->cnt;
        }

        return $out;
    }

    /**
     * Compte des produits par marque dans un query de base arbitraire, après
     * application des filtres actuels (features + collections). Permet un
     * recount dynamique de la facette marque.
     *
     * @param  Builder<Product>  $baseQuery
     * @return array<int, int> [brand_id => count]
     */
    public function brandCountsForContext(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)->toBase()
            ->whereNotNull('lunar_products.brand_id')
            ->groupBy('lunar_products.brand_id')
            ->selectRaw('lunar_products.brand_id, COUNT(DISTINCT lunar_products.id) as cnt')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->brand_id] = (int) $row->cnt;
        }

        return $out;
    }

    /**
     * Face-counts per family/value for products belonging to a collection.
     *
     * Returns a nested array [family_id => [value_id => count]] suitable for
     * rendering side facets. Phase 3 consumers will wrap this in Redis cache.
     *
     * @return array<int, array<int, int>>
     */
    public function countsFor(LunarCollection $collection): array
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        $rows = DB::table('pko_feature_value_product as fvp')
            ->join('pko_feature_values as fv', 'fv.id', '=', 'fvp.feature_value_id')
            ->join($prefix.'collection_product as cp', 'cp.product_id', '=', 'fvp.product_id')
            ->where('cp.collection_id', $collection->id)
            ->groupBy('fv.feature_family_id', 'fv.id')
            ->selectRaw('fv.feature_family_id, fv.id as value_id, COUNT(DISTINCT fvp.product_id) as cnt')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->feature_family_id][(int) $row->value_id] = (int) $row->cnt;
        }

        return $out;
    }
}
