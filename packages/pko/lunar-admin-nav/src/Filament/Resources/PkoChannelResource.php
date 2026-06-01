<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\ChannelResource;
use Pko\AdminNav\Filament\Clusters\PkoShopPaymentCluster;

class PkoChannelResource extends ChannelResource
{
    protected static ?string $slug = 'channels';

    protected static ?string $cluster = PkoShopPaymentCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoChannelResource\Pages\PkoListChannels::route('/'),
            'create' => PkoChannelResource\Pages\PkoCreateChannel::route('/create'),
            'edit' => PkoChannelResource\Pages\PkoEditChannel::route('/{record}/edit'),
        ];
    }
}
