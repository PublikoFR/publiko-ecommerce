<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource;

class EditShippingSurcharge extends BaseEditRecord
{
    protected static string $resource = ShippingSurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
