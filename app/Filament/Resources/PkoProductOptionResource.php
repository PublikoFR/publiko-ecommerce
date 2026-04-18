<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductOptionResource;

class PkoProductOptionResource extends ProductOptionResource
{
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Paramètres catalogue';
    }

    public static function getNavigationLabel(): string
    {
        return 'Options de produits';
    }
}
