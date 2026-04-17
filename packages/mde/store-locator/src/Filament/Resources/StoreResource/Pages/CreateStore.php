<?php

declare(strict_types=1);

namespace Mde\StoreLocator\Filament\Resources\StoreResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Mde\StoreLocator\Filament\Resources\StoreResource;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;
}
