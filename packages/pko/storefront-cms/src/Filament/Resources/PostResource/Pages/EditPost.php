<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PostResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;
use Pko\StorefrontCms\Filament\Resources\PostResource;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected static string $view = 'page-builder::filament.edit-with-builder';

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        Cache::forget('pko.home.posts.v1');
    }
}
