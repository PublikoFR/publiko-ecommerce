<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PageResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Pko\StorefrontCms\Filament\Resources\PageResource;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
