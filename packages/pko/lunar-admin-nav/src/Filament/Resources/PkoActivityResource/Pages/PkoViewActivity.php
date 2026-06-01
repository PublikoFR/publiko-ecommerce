<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoActivityResource\Pages;

use Lunar\Admin\Filament\Resources\ActivityResource\Pages\ViewActivity;
use Pko\AdminNav\Filament\Resources\PkoActivityResource;

class PkoViewActivity extends ViewActivity
{
    protected static string $resource = PkoActivityResource::class;
}
