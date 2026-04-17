<?php

declare(strict_types=1);

namespace Mde\StoreLocator\Filament\Resources\StoreResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Mde\StoreLocator\Filament\Resources\StoreResource;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
