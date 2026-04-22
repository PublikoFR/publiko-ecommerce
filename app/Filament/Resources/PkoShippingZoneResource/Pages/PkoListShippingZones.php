<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingZoneResource\Pages;

use App\Filament\Resources\PkoShippingZoneResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;

class PkoListShippingZones extends ListShippingZones
{
    protected static string $resource = PkoShippingZoneResource::class;
}
