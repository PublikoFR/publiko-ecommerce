<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoStaffResource\Pages;

use Lunar\Admin\Filament\Resources\StaffResource\Pages\AccessControl;
use Pko\AdminNav\Filament\Resources\PkoStaffResource;

class PkoAccessControl extends AccessControl
{
    protected static string $resource = PkoStaffResource::class;
}
