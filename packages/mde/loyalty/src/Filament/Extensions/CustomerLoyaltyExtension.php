<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Extensions;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Mde\Loyalty\Filament\RelationManagers\CustomerGiftHistoryRelationManager;
use Mde\Loyalty\Filament\RelationManagers\CustomerPointsHistoryRelationManager;

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
