<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoShippingMethodResource\Pages;

use Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages\ListShippingMethod;
use Pko\AdminNav\Filament\Resources\PkoShippingMethodResource;
use Pko\AdminNav\Filament\Support\ShippingSubNavigation;

class PkoListShippingMethod extends ListShippingMethod
{
    protected static string $resource = PkoShippingMethodResource::class;

    public function getSubNavigation(): array
    {
        return ShippingSubNavigation::items();
    }
}
