<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Models\FeatureValue;
use Pko\CatalogFeatures\Services\FeatureManager;

/**
 * @method static void attach(Product $product, FeatureValue|int $value)
 * @method static void detach(Product $product, FeatureValue|int $value)
 * @method static array sync(Product $product, array $valueIds)
 * @method static array syncByHandles(Product $product, array $familyHandleToValueHandles)
 * @method static Collection for(Product $product)
 * @method static EloquentCollection familiesFor(Product $product)
 * @method static Builder productsWith(array $valueIds)
 * @method static array countsFor(LunarCollection $collection)
 * @method static array countsForContext(Builder $baseQuery, array $selectedByFamily, ?int $excludeFamilyId = null)
 * @method static array brandCountsForContext(Builder $baseQuery)
 */
class Features extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureManager::class;
    }
}
