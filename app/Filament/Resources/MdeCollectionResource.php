<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Lunar\Admin\Filament\Resources\CollectionResource;
use Lunar\Admin\Filament\Resources\CollectionResource\Pages\ManageCollectionMedia;

class MdeCollectionResource extends CollectionResource
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
            fn (string $page) => $page !== ManageCollectionMedia::class,
        ));
    }
}
