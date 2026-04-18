<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Resources\LoyaltyTierResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource;

class CreateLoyaltyTier extends BaseCreateRecord
{
    protected static string $resource = LoyaltyTierResource::class;
}
