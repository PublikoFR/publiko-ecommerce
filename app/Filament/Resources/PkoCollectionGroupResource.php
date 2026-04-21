<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoCollectionGroupResource\Pages\PkoCreateCollectionGroup;
use App\Filament\Resources\PkoCollectionGroupResource\Pages\PkoEditCollectionGroup;
use App\Filament\Resources\PkoCollectionGroupResource\Pages\PkoListCollectionGroups;
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

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListCollectionGroups::route('/'),
            'create' => PkoCreateCollectionGroup::route('/create'),
            'edit' => PkoEditCollectionGroup::route('/{record}/edit'),
        ];
    }
}
