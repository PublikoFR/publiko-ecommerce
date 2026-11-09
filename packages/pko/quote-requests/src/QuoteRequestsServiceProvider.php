<?php

declare(strict_types=1);

namespace Pko\QuoteRequests;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Pko\QuoteRequests\Livewire\QuoteRequestPage;

class QuoteRequestsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/quote-requests.php', 'quote-requests');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-quote-requests');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-quote-requests');

        $this->publishes([
            __DIR__.'/../config/quote-requests.php' => config_path('quote-requests.php'),
        ], 'pko-quote-requests-config');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/pko-quote-requests'),
        ], 'pko-quote-requests-lang');

        Livewire::component('quote-requests.page', QuoteRequestPage::class);
    }
}
