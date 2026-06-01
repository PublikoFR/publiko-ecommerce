<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoStaffResource\Pages;

use Lunar\Admin\Filament\Resources\StaffResource\Pages\ListStaff;
use Pko\AdminNav\Filament\Resources\PkoStaffResource;

class PkoListStaff extends ListStaff
{
    protected static string $resource = PkoStaffResource::class;
}
