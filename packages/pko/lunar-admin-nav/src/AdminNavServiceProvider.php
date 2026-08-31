<?php

declare(strict_types=1);

namespace Pko\AdminNav;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Pko\AdminNav\Filament\Widgets\HomeOffersTable;
use Pko\AdminNav\Filament\Widgets\HomeSlidesTable;
use Pko\AdminNav\Filament\Widgets\HomeTilesTable;
use Pko\AdminNav\Filament\Widgets\LoyaltyGiftsTable;
use Pko\AdminNav\Filament\Widgets\LoyaltyPointsTable;
use Pko\AdminNav\Filament\Widgets\LoyaltyTiersTable;
use Pko\AdminNav\Livewire\MaintenanceToggle;

class AdminNavServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-nav.php', 'admin-nav');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'admin-nav');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'admin-nav');

        Livewire::component('admin-nav::maintenance-toggle', MaintenanceToggle::class);

        // Widgets injectés via @livewire(...::class) dans les hubs (homepage-hub,
        // loyalty-hub) : sans enregistrement explicite, le nom auto-généré par
        // Livewire au rendu initial ne se résout plus lors d'une action AJAX
        // (ex: suppression de ligne dans la table) → ComponentNotFoundException.
        Livewire::component('admin-nav::home-slides-table', HomeSlidesTable::class);
        Livewire::component('admin-nav::home-tiles-table', HomeTilesTable::class);
        Livewire::component('admin-nav::home-offers-table', HomeOffersTable::class);
        Livewire::component('admin-nav::loyalty-tiers-table', LoyaltyTiersTable::class);
        Livewire::component('admin-nav::loyalty-gifts-table', LoyaltyGiftsTable::class);
        Livewire::component('admin-nav::loyalty-points-table', LoyaltyPointsTable::class);
    }
}
