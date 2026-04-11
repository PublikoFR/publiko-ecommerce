<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource;

class EditFeatureFamily extends BaseEditRecord
{
    protected static string $resource = FeatureFamilyResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
