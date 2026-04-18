<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Pko\StorefrontCms\Filament\Resources\PageResource;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
