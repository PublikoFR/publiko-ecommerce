<?php

declare(strict_types=1);

namespace Pko\StoreLocator\Filament\Resources\StoreResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Pko\StoreLocator\Filament\Resources\StoreResource;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;
}
