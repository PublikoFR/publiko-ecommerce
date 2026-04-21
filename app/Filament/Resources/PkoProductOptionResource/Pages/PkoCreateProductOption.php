<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoProductOptionResource\Pages;

use App\Filament\Resources\PkoProductOptionResource;
use Lunar\Admin\Filament\Resources\ProductOptionResource\Pages\CreateProductOption;

class PkoCreateProductOption extends CreateProductOption
{
    protected static string $resource = PkoProductOptionResource::class;
}
