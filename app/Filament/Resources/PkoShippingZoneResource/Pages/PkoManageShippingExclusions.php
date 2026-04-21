<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingZoneResource\Pages;

use App\Filament\Resources\PkoShippingZoneResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\ManageShippingExclusions;

class PkoManageShippingExclusions extends ManageShippingExclusions
{
    protected static string $resource = PkoShippingZoneResource::class;
}
