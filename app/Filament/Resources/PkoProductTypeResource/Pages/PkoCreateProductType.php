<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoProductTypeResource\Pages;

use App\Filament\Resources\PkoProductTypeResource;
use Lunar\Admin\Filament\Resources\ProductTypeResource\Pages\CreateProductType;

class PkoCreateProductType extends CreateProductType
{
    protected static string $resource = PkoProductTypeResource::class;
}
