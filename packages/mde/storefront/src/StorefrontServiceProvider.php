<?php

declare(strict_types=1);

namespace Mde\Storefront;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mde-storefront.php', 'mde-storefront');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storefront');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components');

        $this->publishes([
            __DIR__.'/../config/mde-storefront.php' => config_path('mde-storefront.php'),
        ], 'mde-storefront-config');
    }
}
