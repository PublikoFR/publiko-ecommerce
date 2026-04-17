<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Resources\HomeTileResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Cache;
use Mde\StorefrontCms\Filament\Resources\HomeTileResource;

class ManageHomeTiles extends ManageRecords
{
    protected static string $resource = HomeTileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn () => Cache::forget('mde.home.tiles.v1'))];
    }

    protected function afterSave(): void
    {
        Cache::forget('mde.home.tiles.v1');
    }
}
