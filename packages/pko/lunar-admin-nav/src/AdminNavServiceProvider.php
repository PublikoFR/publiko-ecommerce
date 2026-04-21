<?php

declare(strict_types=1);

namespace Pko\AdminNav;

use Illuminate\Support\ServiceProvider;

class AdminNavServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'admin-nav');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'admin-nav');
    }
}
