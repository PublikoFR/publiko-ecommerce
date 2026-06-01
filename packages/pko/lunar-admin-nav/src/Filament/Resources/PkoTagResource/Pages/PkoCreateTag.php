<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTagResource\Pages;

use Lunar\Admin\Filament\Resources\TagResource\Pages\CreateTag;
use Pko\AdminNav\Filament\Resources\PkoTagResource;

class PkoCreateTag extends CreateTag
{
    protected static string $resource = PkoTagResource::class;
}
