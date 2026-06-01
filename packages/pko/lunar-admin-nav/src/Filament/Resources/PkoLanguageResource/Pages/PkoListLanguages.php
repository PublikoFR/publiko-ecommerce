<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoLanguageResource\Pages;

use Lunar\Admin\Filament\Resources\LanguageResource\Pages\ListLanguages;
use Pko\AdminNav\Filament\Resources\PkoLanguageResource;

class PkoListLanguages extends ListLanguages
{
    protected static string $resource = PkoLanguageResource::class;
}
