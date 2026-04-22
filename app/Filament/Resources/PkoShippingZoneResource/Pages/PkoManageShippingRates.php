<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingZoneResource\Pages;

use App\Filament\Resources\PkoShippingZoneResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\ManageShippingRates;

class PkoManageShippingRates extends ManageShippingRates
{
    protected static string $resource = PkoShippingZoneResource::class;
}
