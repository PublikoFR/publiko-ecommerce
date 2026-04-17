<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Resources\LoyaltyTierResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Mde\Loyalty\Filament\Resources\LoyaltyTierResource;

class ListLoyaltyTiers extends BaseListRecords
{
    protected static string $resource = LoyaltyTierResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
