<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Filament\Resources\FeatureFamilyResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Pko\CatalogFeatures\Filament\Resources\FeatureFamilyResource;

class ListFeatureFamilies extends BaseListRecords
{
    protected static string $resource = FeatureFamilyResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
