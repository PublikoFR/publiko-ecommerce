<?php

declare(strict_types=1);

namespace Pko\LunarMediaCore;

use Illuminate\Support\ServiceProvider;

class LunarMediaCoreServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'media-core');
    }
}
