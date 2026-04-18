<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\HomeOfferResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Cache;
use Pko\StorefrontCms\Filament\Resources\HomeOfferResource;

class ManageHomeOffers extends ManageRecords
{
    protected static string $resource = HomeOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn () => Cache::forget('pko.home.offers.v1'))];
    }

    protected function afterSave(): void
    {
        Cache::forget('pko.home.offers.v1');
    }
}
