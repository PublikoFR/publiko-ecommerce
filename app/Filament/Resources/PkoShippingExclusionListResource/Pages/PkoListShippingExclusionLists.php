<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoShippingExclusionListResource\Pages;

use App\Filament\Resources\PkoShippingExclusionListResource;
use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource\Pages\ListShippingExclusionLists;

class PkoListShippingExclusionLists extends ListShippingExclusionLists
{
    protected static string $resource = PkoShippingExclusionListResource::class;
}
