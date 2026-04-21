<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingMethodResource\Pages;

use App\Filament\Resources\PkoShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages\ManageShippingMethodAvailability;

class PkoManageShippingMethodAvailability extends ManageShippingMethodAvailability
{
    protected static string $resource = PkoShippingMethodResource::class;
}
