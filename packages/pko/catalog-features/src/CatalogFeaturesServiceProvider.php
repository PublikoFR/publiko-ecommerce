<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Models\BrandFamily;
use Pko\CatalogFeatures\Models\FeatureValue;
use Pko\CatalogFeatures\Models\ProductCompatibility;
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

        // Famille fabricant (privée) — colonne custom `pko_brand_family_id`.
        Product::resolveRelationUsing(
            'brandFamily',
            fn (Product $product) => $product->belongsTo(BrandFamily::class, 'pko_brand_family_id'),
        );

        // Compatibilités documentées pièce ↔ équipements (table pko_product_compatibilities).
        Product::resolveRelationUsing(
            'compatibilities',
            fn (Product $product) => $product->hasMany(ProductCompatibility::class, 'product_id')
                ->orderBy('sort_order')
                ->orderBy('id'),
        );
    }
}
