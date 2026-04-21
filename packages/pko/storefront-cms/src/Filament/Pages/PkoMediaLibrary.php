<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Coquille Filament Page — la logique et le rendu vivent dans
 * le composant Livewire unifié `Pko\StorefrontCms\Livewire\PkoMediaLibrary`
 * (alias Livewire `pko-media-library`), partagé avec la modale picker.
 */
class PkoMediaLibrary extends Page
{
    protected static ?string $navigationGroup = 'Storefront';

    public static function getNavigationLabel(): string
    {
        return __('pko-storefront-cms::admin.media_library.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pko-storefront-cms::admin.media_library.title');
    }

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'mediatheque';

    protected static string $view = 'storefront-cms::filament.pages.media-library';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
