<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Filament\Pages;

use Pko\ShippingCommon\Filament\Pages\AbstractCarrierConfigPage;

class ChronopostConfig extends AbstractCarrierConfigPage
{
    protected static string $view = 'pko-shipping-common::pages.carrier-config';

    protected static ?int $navigationSort = 20;

    protected function carrierCode(): string
    {
        return 'chronopost';
    }

    protected static function navigationLabel(): ?string
    {
        return 'Chronopost';
    }
}
