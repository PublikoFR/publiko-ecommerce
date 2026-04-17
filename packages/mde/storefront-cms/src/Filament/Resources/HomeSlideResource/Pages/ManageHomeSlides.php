<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Resources\HomeSlideResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Cache;
use Mde\StorefrontCms\Filament\Resources\HomeSlideResource;

class ManageHomeSlides extends ManageRecords
{
    protected static string $resource = HomeSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->after(fn () => Cache::forget('mde.home.slides.v1')),
        ];
    }

    protected function afterSave(): void
    {
        Cache::forget('mde.home.slides.v1');
    }
}
