<?php

declare(strict_types=1);

namespace Pko\ProductVideos;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Pko\ProductVideos\Models\ProductVideo;
use Pko\ProductVideos\View\Components\VideoEmbed;

class ProductVideosServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'product-videos');

        Blade::component('pko-product-video', VideoEmbed::class);
        Blade::componentNamespace('Pko\\ProductVideos\\View\\Components', 'product-videos');

        Product::resolveRelationUsing(
            'videos',
            fn (Product $product) => $product->hasMany(ProductVideo::class)->orderBy('sort_order'),
        );

        if (class_exists(FilamentAsset::class)) {
            FilamentAsset::register([
                Js::make('pko-product-videos-sortable', __DIR__.'/../resources/js/sortable-init.js'),
            ], 'pko/product-videos');
        }
    }
}
