<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;
use Mde\StorefrontCms\Filament\Resources\PostResource;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function afterCreate(): void
    {
        Cache::forget('mde.home.posts.v1');
    }
}
