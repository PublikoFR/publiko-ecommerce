<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Resources\GiftHistoryResource\Pages;

use Lunar\Admin\Support\Pages\BaseEditRecord;
use Pko\Loyalty\Filament\Resources\GiftHistoryResource;

class EditGiftHistory extends BaseEditRecord
{
    protected static string $resource = GiftHistoryResource::class;
}
