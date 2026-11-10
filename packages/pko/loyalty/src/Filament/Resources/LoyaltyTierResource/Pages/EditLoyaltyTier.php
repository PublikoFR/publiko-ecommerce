<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Resources\LoyaltyTierResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource;

class EditLoyaltyTier extends BaseEditRecord
{
    protected static string $resource = LoyaltyTierResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return parent::mutateFormDataBeforeSave(LoyaltyTierResource::clearOtherTypeFields($data));
    }
}
