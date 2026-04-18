<?php

declare(strict_types=1);

namespace Pko\Storefront;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Pko\Storefront\Livewire\CartDrawer;
use Pko\Storefront\Livewire\SearchAutocomplete;

class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'storefront');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storefront');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components');

        Livewire::component('storefront.search-autocomplete', SearchAutocomplete::class);
        Livewire::component('storefront.cart-drawer', CartDrawer::class);

        $this->publishes([
            __DIR__.'/../config/storefront.php' => config_path('storefront.php'),
        ], 'storefront-config');
    }
}
