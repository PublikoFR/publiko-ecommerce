<?php

declare(strict_types=1);

namespace Pko\AiFilament;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class AiFilamentServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-filament');

        FilamentAsset::register([
            Css::make('pko-ai-filament-split-button', __DIR__.'/../resources/css/split-button.css'),
        ], 'pko/ai-filament');
    }
}
