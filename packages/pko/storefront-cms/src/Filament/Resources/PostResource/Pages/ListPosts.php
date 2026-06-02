<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PostResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;
use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Filament\Resources\PostTypeResource;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // « Type de contenus » déplacé ici (bouton header) plutôt que dans le menu.
            Action::make('postTypes')
                ->label('Types de contenus')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->url(fn (): string => PostTypeResource::getUrl()),
            CreateAction::make()->after(fn () => Cache::forget('pko.home.posts.v1')),
        ];
    }
}
