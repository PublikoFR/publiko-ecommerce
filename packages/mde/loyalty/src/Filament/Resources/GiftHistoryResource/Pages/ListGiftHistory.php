<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Resources\GiftHistoryResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Mde\Loyalty\Filament\Resources\GiftHistoryResource;

class ListGiftHistory extends BaseListRecords
{
    protected static string $resource = GiftHistoryResource::class;
}
