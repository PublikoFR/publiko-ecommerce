<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Pko\StorefrontCms\Filament\Resources\PageResource;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
