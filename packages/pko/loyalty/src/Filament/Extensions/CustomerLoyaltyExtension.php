<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Extensions;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\Loyalty\Filament\RelationManagers\CustomerGiftHistoryRelationManager;
use Pko\Loyalty\Filament\RelationManagers\CustomerPointsHistoryRelationManager;

class CustomerLoyaltyExtension extends ResourceExtension
{
    public function getRelations(array $managers): array
    {
        return [
            ...$managers,
            CustomerGiftHistoryRelationManager::class,
            CustomerPointsHistoryRelationManager::class,
        ];
    }
}
