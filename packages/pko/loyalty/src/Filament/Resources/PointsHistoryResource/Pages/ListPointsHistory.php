<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Resources\PointsHistoryResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Pko\Loyalty\Filament\Resources\PointsHistoryResource;

class ListPointsHistory extends BaseListRecords
{
    protected static string $resource = PointsHistoryResource::class;
}
