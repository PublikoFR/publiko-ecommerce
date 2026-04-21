<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\ShippingChronopost\Filament\Pages\ChronopostConfig;

class ChronopostPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-shipping-chronopost';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            ChronopostConfig::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
