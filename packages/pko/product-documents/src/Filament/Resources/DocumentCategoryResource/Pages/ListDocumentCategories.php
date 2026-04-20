<?php

declare(strict_types=1);

namespace Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource;

class ListDocumentCategories extends BaseListRecords
{
    protected static string $resource = DocumentCategoryResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
