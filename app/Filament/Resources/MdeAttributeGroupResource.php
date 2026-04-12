<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Lunar\Admin\Filament\Resources\AttributeGroupResource;

class MdeAttributeGroupResource extends AttributeGroupResource
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
}
