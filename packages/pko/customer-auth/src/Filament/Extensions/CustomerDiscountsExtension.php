<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Extensions;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\RelationManagers\CustomerDiscountsRelationManager;

/** Onglet « Remises » sur la fiche client, après « Prix négociés ». */
class CustomerDiscountsExtension extends ResourceExtension
{
    public function getRelations(array $managers): array
    {
        return [
            ...$managers,
            CustomerDiscountsRelationManager::class,
        ];
    }
}
