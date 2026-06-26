<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources\SupplierResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Pko\ShippingCommon\Filament\Resources\SupplierResource;

class ListSuppliers extends BaseListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
