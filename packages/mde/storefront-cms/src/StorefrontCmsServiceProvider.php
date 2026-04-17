<?php

declare(strict_types=1);

namespace Mde\StorefrontCms;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mde\StorefrontCms\Livewire\HomeFeaturedProducts;
use Mde\StorefrontCms\Livewire\HomeHero;
use Mde\StorefrontCms\Livewire\HomeOffers;
use Mde\StorefrontCms\Livewire\HomePosts;
use Mde\StorefrontCms\Livewire\HomeTiles;

class StorefrontCmsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storefront-cms');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Livewire::component('storefront-cms.home-hero', HomeHero::class);
        Livewire::component('storefront-cms.home-tiles', HomeTiles::class);
        Livewire::component('storefront-cms.home-offers', HomeOffers::class);
        Livewire::component('storefront-cms.home-posts', HomePosts::class);
        Livewire::component('storefront-cms.home-featured', HomeFeaturedProducts::class);
    }
}
