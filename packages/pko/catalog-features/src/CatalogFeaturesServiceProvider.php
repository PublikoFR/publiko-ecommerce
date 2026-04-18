<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Models\FeatureValue;
use Pko\CatalogFeatures\Services\FeatureManager;

class CatalogFeaturesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureManager::class, fn () => new FeatureManager);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Product::resolveRelationUsing(
            'featureValues',
            fn (Product $product) => $product->belongsToMany(
                FeatureValue::class,
                'pko_feature_value_product',
                'product_id',
                'feature_value_id',
            ),
        );
    }
}
