<?php

declare(strict_types=1);

namespace Pko\PageBuilder;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Pko\PageBuilder\Livewire\PageBuilder;

class PageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'page-builder');

        Blade::componentNamespace('Pko\\PageBuilder\\View\\Components', 'page-builder');

        if (class_exists(Livewire::class)) {
            Livewire::component('pko-page-builder', PageBuilder::class);
        }

        if (class_exists(FilamentAsset::class)) {
            FilamentAsset::register([
                Js::make('pko-page-builder-sortable', __DIR__.'/../resources/js/sortable-init.js'),
            ], 'pko/page-builder');
        }
    }
}
