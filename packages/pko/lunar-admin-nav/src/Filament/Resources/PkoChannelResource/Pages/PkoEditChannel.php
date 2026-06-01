<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoChannelResource\Pages;

use Lunar\Admin\Filament\Resources\ChannelResource\Pages\EditChannel;
use Pko\AdminNav\Filament\Resources\PkoChannelResource;

class PkoEditChannel extends EditChannel
{
    protected static string $resource = PkoChannelResource::class;
}
