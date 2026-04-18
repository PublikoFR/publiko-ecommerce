<?php

declare(strict_types=1);

namespace Pko\StoreLocator\Filament\Resources\StoreResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Pko\StoreLocator\Filament\Resources\StoreResource;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
