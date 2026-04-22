<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingZoneResource\Pages;

use App\Filament\Resources\PkoShippingZoneResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;

class PkoEditShippingZone extends EditShippingZone
{
    protected static string $resource = PkoShippingZoneResource::class;
}
