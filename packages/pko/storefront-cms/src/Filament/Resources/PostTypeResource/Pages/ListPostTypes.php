<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PostTypeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Pko\StorefrontCms\Filament\Resources\PostTypeResource;

class ListPostTypes extends ListRecords
{
    protected static string $resource = PostTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
