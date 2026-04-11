<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource;

class CreateFeatureFamily extends BaseCreateRecord
{
    protected static string $resource = FeatureFamilyResource::class;
}
