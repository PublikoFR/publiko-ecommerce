<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\LanguageResource;
use Pko\AdminNav\Filament\Clusters\PkoShopPaymentCluster;

class PkoLanguageResource extends LanguageResource
{
    protected static ?string $slug = 'languages';

    protected static ?string $cluster = PkoShopPaymentCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoLanguageResource\Pages\PkoListLanguages::route('/'),
            'create' => PkoLanguageResource\Pages\PkoCreateLanguage::route('/create'),
            'edit' => PkoLanguageResource\Pages\PkoEditLanguage::route('/{record}/edit'),
        ];
    }
}
