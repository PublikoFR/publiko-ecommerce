<?php

declare(strict_types=1);

namespace Pko\StoreLocator\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\StoreLocator\Filament\Resources\StoreResource;

class StoreLocatorPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mde-store-locator';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([StoreResource::class]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
