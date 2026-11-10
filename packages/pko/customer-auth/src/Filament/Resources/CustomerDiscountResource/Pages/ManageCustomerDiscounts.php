<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\CustomerDiscountResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Pko\CustomerAuth\Filament\Resources\CustomerDiscountResource;

class ManageCustomerDiscounts extends ManageRecords
{
    protected static string $resource = CustomerDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajouter une remise'),
        ];
    }
}
