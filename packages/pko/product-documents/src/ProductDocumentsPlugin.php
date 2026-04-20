<?php

declare(strict_types=1);

namespace Pko\ProductDocuments;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource;

class ProductDocumentsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-product-documents';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            DocumentCategoryResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
