<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Pko\AdminNav\Filament\Support\ShippingSubNavigation;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;

class ListCarrierShipments extends BaseListRecords
{
    protected static string $resource = CarrierShipmentResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [];
    }

    public function getSubNavigation(): array
    {
        if (class_exists(ShippingSubNavigation::class)) {
            return ShippingSubNavigation::items();
        }

        return [];
    }
}
