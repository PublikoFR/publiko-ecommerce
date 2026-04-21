<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoCollectionGroupResource\Pages;

use App\Filament\Resources\PkoCollectionGroupResource;
use Lunar\Admin\Filament\Resources\CollectionGroupResource\Pages\CreateCollectionGroup;

class PkoCreateCollectionGroup extends CreateCollectionGroup
{
    protected static string $resource = PkoCollectionGroupResource::class;
}
