<?php

declare(strict_types=1);

namespace Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource;

class CreateDocumentCategory extends BaseCreateRecord
{
    protected static string $resource = DocumentCategoryResource::class;
}
