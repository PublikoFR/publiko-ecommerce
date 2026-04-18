<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\ShippingColissimo\Filament\Pages\ColissimoConfig;

class ColissimoPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mde-shipping-colissimo';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            ColissimoConfig::class,
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
