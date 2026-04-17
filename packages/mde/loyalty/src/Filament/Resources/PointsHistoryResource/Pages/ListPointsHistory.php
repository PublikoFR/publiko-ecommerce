<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Resources\PointsHistoryResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Mde\Loyalty\Filament\Resources\PointsHistoryResource;

class ListPointsHistory extends BaseListRecords
{
    protected static string $resource = PointsHistoryResource::class;
}
