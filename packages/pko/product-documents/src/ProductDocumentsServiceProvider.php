<?php

declare(strict_types=1);

namespace Pko\ProductDocuments;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Pko\ProductDocuments\Models\ProductDocument;

class ProductDocumentsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'product-documents');

        Blade::componentNamespace('Pko\\ProductDocuments\\View\\Components', 'product-documents');

        Product::resolveRelationUsing(
            'documents',
            fn (Product $product) => $product->hasMany(ProductDocument::class)->orderBy('sort_order'),
        );
    }
}
