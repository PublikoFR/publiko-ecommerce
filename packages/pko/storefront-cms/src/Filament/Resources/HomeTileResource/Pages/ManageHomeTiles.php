<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\HomeTileResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Cache;
use Pko\StorefrontCms\Filament\Resources\HomeTileResource;

class ManageHomeTiles extends ManageRecords
{
    protected static string $resource = HomeTileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn () => Cache::forget('pko.home.tiles.v1'))];
    }

    protected function afterSave(): void
    {
        Cache::forget('pko.home.tiles.v1');
    }
}
