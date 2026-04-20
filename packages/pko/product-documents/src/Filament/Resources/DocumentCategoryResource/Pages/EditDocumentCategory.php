<?php

declare(strict_types=1);

namespace Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource;

class EditDocumentCategory extends BaseEditRecord
{
    protected static string $resource = DocumentCategoryResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
