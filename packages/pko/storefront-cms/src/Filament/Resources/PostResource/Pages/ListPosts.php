<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PostResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;
use Pko\StorefrontCms\Filament\Resources\PostResource;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn () => Cache::forget('pko.home.posts.v1'))];
    }
}
