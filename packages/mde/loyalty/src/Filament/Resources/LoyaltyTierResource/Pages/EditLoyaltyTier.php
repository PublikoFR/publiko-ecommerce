<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Resources\LoyaltyTierResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Mde\Loyalty\Filament\Resources\LoyaltyTierResource;

class EditLoyaltyTier extends BaseEditRecord
{
    protected static string $resource = LoyaltyTierResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
