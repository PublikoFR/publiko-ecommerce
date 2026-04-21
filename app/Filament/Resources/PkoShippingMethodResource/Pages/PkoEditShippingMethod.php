<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingMethodResource\Pages;

use App\Filament\Resources\PkoShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages\EditShippingMethod;

class PkoEditShippingMethod extends EditShippingMethod
{
    protected static string $resource = PkoShippingMethodResource::class;
}
