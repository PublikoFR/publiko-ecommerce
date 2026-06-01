<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\StaffResource;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;

class PkoStaffResource extends StaffResource
{
    protected static ?string $slug = 'staff';

    protected static ?string $cluster = PkoSystemDataCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoStaffResource\Pages\PkoListStaff::route('/'),
            'acl' => PkoStaffResource\Pages\PkoAccessControl::route('/access-control'),
            'create' => PkoStaffResource\Pages\PkoCreateStaff::route('/create'),
            'edit' => PkoStaffResource\Pages\PkoEditStaff::route('/{record}/edit'),
        ];
    }
}
