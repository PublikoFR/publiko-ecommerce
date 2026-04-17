<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Mde\StorefrontCms\Filament\Resources\PageResource;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
