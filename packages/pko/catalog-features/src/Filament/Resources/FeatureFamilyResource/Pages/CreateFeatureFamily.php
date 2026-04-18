<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Filament\Resources\FeatureFamilyResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Pko\CatalogFeatures\Filament\Resources\FeatureFamilyResource;

class CreateFeatureFamily extends BaseCreateRecord
{
    protected static string $resource = FeatureFamilyResource::class;
}
