<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoStaffResource\Pages;

use Filament\Actions;
use Filament\Support\Colors\Color;
use Lunar\Admin\Filament\Resources\StaffResource\Pages\ListStaff;
use Pko\AdminNav\Filament\Resources\PkoStaffResource;

class PkoListStaff extends ListStaff
{
    protected static string $resource = PkoStaffResource::class;

    /**
     * Lunar's ListStaff hardcodes StaffResource::getUrl('acl'), whose route is
     * unregistered after the resource swap (PkoStaffResource lives in the
     * systeme-donnees cluster). Rebuild the action against the swapped resource.
     */
    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\Action::make('access-control')
                ->label(__('lunarpanel::staff.action.acl.label'))
                ->color(Color::Lime)
                ->url(fn () => PkoStaffResource::getUrl('acl')),
            Actions\CreateAction::make(),
        ];
    }
}
