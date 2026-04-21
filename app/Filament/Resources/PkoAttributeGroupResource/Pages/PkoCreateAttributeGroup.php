<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoAttributeGroupResource\Pages;

use App\Filament\Resources\PkoAttributeGroupResource;
use Lunar\Admin\Filament\Resources\AttributeGroupResource\Pages\CreateAttributeGroup;

class PkoCreateAttributeGroup extends CreateAttributeGroup
{
    protected static string $resource = PkoAttributeGroupResource::class;
}
