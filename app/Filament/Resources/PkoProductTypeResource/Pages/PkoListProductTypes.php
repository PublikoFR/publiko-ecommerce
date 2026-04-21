<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoProductTypeResource\Pages;

use App\Filament\Resources\PkoProductTypeResource;
use Lunar\Admin\Filament\Resources\ProductTypeResource\Pages\ListProductTypes;

/**
 * Sous-classe avec $resource = PkoProductTypeResource pour que les URLs edit
 * générées depuis la list page utilisent le bon nom de route pko-product-types.*.
 * Cf. [[product-list-admin]] pattern.
 */
class PkoListProductTypes extends ListProductTypes
{
    protected static string $resource = PkoProductTypeResource::class;
}
