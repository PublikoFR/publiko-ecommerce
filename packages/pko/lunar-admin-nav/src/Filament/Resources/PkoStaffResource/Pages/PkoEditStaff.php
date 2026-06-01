<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoStaffResource\Pages;

use Lunar\Admin\Filament\Resources\StaffResource\Pages\EditStaff;
use Pko\AdminNav\Filament\Resources\PkoStaffResource;

class PkoEditStaff extends EditStaff
{
    protected static string $resource = PkoStaffResource::class;
}
