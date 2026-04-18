<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Lunar\Admin\Filament\Resources\CollectionGroupResource;

class PkoCollectionGroupResource extends CollectionGroupResource
{
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Paramètres catalogue';
    }

    public static function getNavigationLabel(): string
    {
        return 'Groupes de collections';
    }
}
