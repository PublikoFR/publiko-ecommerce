<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo\Filament\Pages;

use Pko\ShippingCommon\Filament\Pages\AbstractCarrierConfigPage;

class ColissimoConfig extends AbstractCarrierConfigPage
{
    protected static string $view = 'pko-shipping-common::pages.carrier-config';

    protected static ?int $navigationSort = 21;

    protected function carrierCode(): string
    {
        return 'colissimo';
    }

    protected static function navigationLabel(): ?string
    {
        return 'Colissimo';
    }
}
