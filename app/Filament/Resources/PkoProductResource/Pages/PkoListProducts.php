<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoProductResource\Pages;

use App\Filament\Resources\PkoProductResource;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ListProducts;

/**
 * Sous-classe de ListProducts avec $resource = PkoProductResource::class,
 * nécessaire pour que getTableColumns() de PkoProductResource soit appelé
 * via late-static-binding (Lunar hardcode son propre resource sinon).
 */
class PkoListProducts extends ListProducts
{
    protected static string $resource = PkoProductResource::class;
}
