<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoProductOptionResource\Pages\PkoCreateProductOption;
use App\Filament\Resources\PkoProductOptionResource\Pages\PkoEditProductOption;
use App\Filament\Resources\PkoProductOptionResource\Pages\PkoListProductOptions;
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

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListProductOptions::route('/'),
            'create' => PkoCreateProductOption::route('/create'),
            'edit' => PkoEditProductOption::route('/{record}/edit'),
        ];
    }
}
