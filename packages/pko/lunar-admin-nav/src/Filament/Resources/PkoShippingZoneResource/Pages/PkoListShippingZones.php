<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoShippingZoneResource\Pages;

use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use Pko\AdminNav\Filament\Resources\PkoShippingZoneResource;
use Pko\AdminNav\Filament\Support\ShippingSubNavigation;

class PkoListShippingZones extends ListShippingZones
{
    protected static string $resource = PkoShippingZoneResource::class;

    public function getSubNavigation(): array
    {
        return ShippingSubNavigation::items();
    }
}
