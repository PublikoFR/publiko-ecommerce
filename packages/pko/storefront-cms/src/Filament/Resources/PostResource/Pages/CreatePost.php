<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;
use Pko\StorefrontCms\Filament\Resources\PostResource;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function afterCreate(): void
    {
        Cache::forget('pko.home.posts.v1');
    }
}
