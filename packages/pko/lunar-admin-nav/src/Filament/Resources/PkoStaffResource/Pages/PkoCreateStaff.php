<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoStaffResource\Pages;

use Lunar\Admin\Filament\Resources\StaffResource\Pages\CreateStaff;
use Pko\AdminNav\Filament\Resources\PkoStaffResource;

class PkoCreateStaff extends CreateStaff
{
    protected static string $resource = PkoStaffResource::class;
}
