<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductTypeResource;

class PkoProductTypeResource extends ProductTypeResource
{
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Paramètres catalogue';
    }

    public static function getNavigationParentItem(): ?string
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return 'Types de produits';
    }
}
