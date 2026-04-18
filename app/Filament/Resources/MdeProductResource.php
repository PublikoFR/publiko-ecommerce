<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductMedia;
use Lunar\Admin\Support\RelationManagers\MediaRelationManager;

/**
 * MDE override of Lunar's ProductResource.
 *
 * Removes the native Spatie MediaLibrary "Media" sub-page/tab/relation
 * in favour of the unified `mde_mediables` system (see HasMediaAttachments trait).
 *
 * The underlying model, form, and all other behaviours are inherited unchanged.
 */
class MdeProductResource extends ProductResource
{
    public static function getDefaultPages(): array
    {
        $pages = parent::getDefaultPages();
        unset($pages['media']);

        return $pages;
    }

    public static function getDefaultSubNavigation(): array
    {
        return array_values(array_filter(
            parent::getDefaultSubNavigation(),
            fn (string $page) => $page !== ManageProductMedia::class,
        ));
    }

    public static function getDefaultRelations(): array
    {
        return array_values(array_filter(
            parent::getDefaultRelations(),
            fn ($relation) => $relation !== MediaRelationManager::class,
        ));
    }
}
