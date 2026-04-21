<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoAttributeGroupResource\Pages\PkoCreateAttributeGroup;
use App\Filament\Resources\PkoAttributeGroupResource\Pages\PkoEditAttributeGroup;
use App\Filament\Resources\PkoAttributeGroupResource\Pages\PkoListAttributeGroups;
use Lunar\Admin\Filament\Resources\AttributeGroupResource;

class PkoAttributeGroupResource extends AttributeGroupResource
{
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Paramètres catalogue';
    }

    public static function getNavigationLabel(): string
    {
        return "Groupes d'attributs";
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListAttributeGroups::route('/'),
            'create' => PkoCreateAttributeGroup::route('/create'),
            'edit' => PkoEditAttributeGroup::route('/{record}/edit'),
        ];
    }
}
