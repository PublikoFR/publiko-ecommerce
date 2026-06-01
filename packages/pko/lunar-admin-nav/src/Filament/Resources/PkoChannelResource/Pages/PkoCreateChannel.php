<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoChannelResource\Pages;

use Lunar\Admin\Filament\Resources\ChannelResource\Pages\CreateChannel;
use Pko\AdminNav\Filament\Resources\PkoChannelResource;

class PkoCreateChannel extends CreateChannel
{
    protected static string $resource = PkoChannelResource::class;
}
