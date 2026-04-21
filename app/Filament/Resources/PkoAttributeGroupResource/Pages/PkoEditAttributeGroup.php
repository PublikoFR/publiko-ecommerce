<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoAttributeGroupResource\Pages;

use App\Filament\Resources\PkoAttributeGroupResource;
use Lunar\Admin\Filament\Resources\AttributeGroupResource\Pages\EditAttributeGroup;

class PkoEditAttributeGroup extends EditAttributeGroup
{
    protected static string $resource = PkoAttributeGroupResource::class;
}
