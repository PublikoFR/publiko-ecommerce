<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTagResource\Pages;

use Lunar\Admin\Filament\Resources\TagResource\Pages\EditTag;
use Pko\AdminNav\Filament\Resources\PkoTagResource;

class PkoEditTag extends EditTag
{
    protected static string $resource = PkoTagResource::class;
}
