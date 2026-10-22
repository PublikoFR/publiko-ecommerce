<?php

declare(strict_types=1);

namespace Pko\LunarMediaCore;

use Illuminate\Support\ServiceProvider;

class LunarMediaCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media-core.php', 'media-core');
    }

    public function boot(): void
    {
        config(['media-library.max_file_size' => (int) config('media-core.max_file_size')]);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'media-core');
    }
}
