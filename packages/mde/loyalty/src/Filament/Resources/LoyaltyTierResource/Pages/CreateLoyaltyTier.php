<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Resources\LoyaltyTierResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Mde\Loyalty\Filament\Resources\LoyaltyTierResource;

class CreateLoyaltyTier extends BaseCreateRecord
{
    protected static string $resource = LoyaltyTierResource::class;
}
