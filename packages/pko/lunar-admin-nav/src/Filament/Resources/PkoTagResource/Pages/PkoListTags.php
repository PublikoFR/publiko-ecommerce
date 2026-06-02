<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTagResource\Pages;

use Lunar\Admin\Filament\Resources\TagResource\Pages\ListTags;
use Pko\AdminNav\Filament\Resources\PkoTagResource;

class PkoListTags extends ListTags
{
    protected static string $resource = PkoTagResource::class;
}
