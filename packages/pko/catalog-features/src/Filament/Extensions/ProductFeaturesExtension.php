<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Filament\Extensions;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\CatalogFeatures\Filament\RelationManagers\ProductFeaturesRelationManager;

class ProductFeaturesExtension extends ResourceExtension
{
    public function getRelations(array $managers): array
    {
        return [
            ...$managers,
            ProductFeaturesRelationManager::class,
        ];
    }
}
