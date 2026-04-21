<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoShippingExclusionListResource\Pages;

use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource\Pages\ListShippingExclusionLists;
use Pko\AdminNav\Filament\Resources\PkoShippingExclusionListResource;
use Pko\AdminNav\Filament\Support\ShippingSubNavigation;

class PkoListShippingExclusionLists extends ListShippingExclusionLists
{
    protected static string $resource = PkoShippingExclusionListResource::class;

    public function getSubNavigation(): array
    {
        return ShippingSubNavigation::items();
    }
}
