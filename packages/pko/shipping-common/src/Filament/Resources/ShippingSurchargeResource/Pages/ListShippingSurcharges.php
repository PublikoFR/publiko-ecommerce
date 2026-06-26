<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource;

class ListShippingSurcharges extends BaseListRecords
{
    protected static string $resource = ShippingSurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
