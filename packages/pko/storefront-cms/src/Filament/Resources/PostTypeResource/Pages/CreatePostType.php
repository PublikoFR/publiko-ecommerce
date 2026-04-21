<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PostTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Pko\StorefrontCms\Filament\Resources\PostTypeResource;

class CreatePostType extends CreateRecord
{
    protected static string $resource = PostTypeResource::class;
}
