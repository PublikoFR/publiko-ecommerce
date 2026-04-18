<?php

declare(strict_types=1);

namespace Pko\StoreLocator;

use Illuminate\Support\ServiceProvider;

class StoreLocatorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'store-locator');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
