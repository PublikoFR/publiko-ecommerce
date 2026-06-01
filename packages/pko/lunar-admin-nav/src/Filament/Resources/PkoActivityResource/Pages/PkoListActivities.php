<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoActivityResource\Pages;

use Lunar\Admin\Filament\Resources\ActivityResource\Pages\ListActivities;
use Pko\AdminNav\Filament\Resources\PkoActivityResource;

class PkoListActivities extends ListActivities
{
    protected static string $resource = PkoActivityResource::class;
}
