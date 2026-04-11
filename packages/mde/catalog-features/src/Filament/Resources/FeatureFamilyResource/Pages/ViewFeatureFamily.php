<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseViewRecord;
use Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource;

class ViewFeatureFamily extends BaseViewRecord
{
    protected static string $resource = FeatureFamilyResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
