<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoChannelResource\Pages;

use Lunar\Admin\Filament\Resources\ChannelResource\Pages\ListChannels;
use Pko\AdminNav\Filament\Resources\PkoChannelResource;

class PkoListChannels extends ListChannels
{
    protected static string $resource = PkoChannelResource::class;
}
