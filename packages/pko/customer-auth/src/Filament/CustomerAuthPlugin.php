<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\CustomerAuth\Filament\Resources\CustomerDiscountResource;

class CustomerAuthPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-customer-auth';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            CustomerDiscountResource::class,
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
