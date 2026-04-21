<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;

/**
 * Unified plugin for all PKO carriers.
 *
 * Replaces the previous ShippingCommonPlugin + one-plugin-per-carrier pattern.
 * Reads the CarrierRegistry and registers every declared config page + the
 * shared CarrierShipmentResource in a single pass.
 */
class TransportersPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-transporters';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            CarrierShipmentResource::class,
        ]);

        $registry = app(CarrierRegistry::class);
        $pages = $registry->configPageClasses();

        if ($pages !== []) {
            $panel->pages($pages);
        }
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
