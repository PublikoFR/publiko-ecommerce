<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources\SupplierResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Pko\ShippingCommon\Filament\Resources\SupplierResource;

class EditSupplier extends BaseEditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
