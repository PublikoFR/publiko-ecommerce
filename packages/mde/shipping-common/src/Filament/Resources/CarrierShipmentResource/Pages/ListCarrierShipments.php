<?php

declare(strict_types=1);

namespace Mde\ShippingCommon\Filament\Resources\CarrierShipmentResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Mde\ShippingCommon\Filament\Resources\CarrierShipmentResource;

class ListCarrierShipments extends BaseListRecords
{
    protected static string $resource = CarrierShipmentResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [];
    }
}
