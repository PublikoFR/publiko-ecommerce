<?php

declare(strict_types=1);

namespace Mde\ShippingCommon\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Mde\ShippingCommon\Filament\Resources\CarrierShipmentResource;

class ShippingCommonPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mde-shipping-common';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            CarrierShipmentResource::class,
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
