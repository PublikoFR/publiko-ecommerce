<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingMethodResource\Pages;

use App\Filament\Resources\PkoShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages\ListShippingMethod;

class PkoListShippingMethod extends ListShippingMethod
{
    protected static string $resource = PkoShippingMethodResource::class;
}
