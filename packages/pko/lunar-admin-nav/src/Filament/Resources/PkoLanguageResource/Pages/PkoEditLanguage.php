<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoLanguageResource\Pages;

use Lunar\Admin\Filament\Resources\LanguageResource\Pages\EditLanguage;
use Pko\AdminNav\Filament\Resources\PkoLanguageResource;

class PkoEditLanguage extends EditLanguage
{
    protected static string $resource = PkoLanguageResource::class;
}
