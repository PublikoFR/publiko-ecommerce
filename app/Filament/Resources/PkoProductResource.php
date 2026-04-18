<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductResource\Pages;

/**
 * Override de la Resource produit Lunar pour substituer l'édition multi-onglets
 * par une page unifiée 2 colonnes. La sous-navigation est masquée, les autres
 * sous-pages Lunar restent accessibles par URL directe (compatibilité).
 */
class PkoProductResource extends ProductResource
{
    protected static ?string $slug = 'products';

    public static function getDefaultSubNavigation(): array
    {
        return [];
    }

    public static function getDefaultPages(): array
    {
        return array_merge(parent::getDefaultPages(), [
            'edit' => EditProductUnified::route('/{record}/edit'),
        ]);
    }
}
