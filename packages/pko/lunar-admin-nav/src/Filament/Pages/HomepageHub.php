<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class HomepageHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static string $view = 'admin-nav::pages.homepage-hub';

    protected static ?string $slug = 'page-accueil';

    #[Url(as: 'tab')]
    public string $activeTab = 'slides';

    public static function getNavigationLabel(): string
    {
        return __('admin-nav::admin.hubs.homepage.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin-nav::admin.hubs.homepage.title');
    }

    /**
     * @return array<string, string>
     */
    public function getTabs(): array
    {
        return [
            'slides' => __('admin-nav::admin.hubs.homepage.tabs.slides'),
            'tiles' => __('admin-nav::admin.hubs.homepage.tabs.tiles'),
            'offers' => __('admin-nav::admin.hubs.homepage.tabs.offers'),
        ];
    }
}
