<?php

declare(strict_types=1);

namespace Pko\StorefrontCms;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Pko\StorefrontCms\Livewire\HomeFeaturedProducts;
use Pko\StorefrontCms\Livewire\HomeHero;
use Pko\StorefrontCms\Livewire\HomeOffers;
use Pko\StorefrontCms\Livewire\HomePosts;
use Pko\StorefrontCms\Livewire\HomeTiles;
use Pko\StorefrontCms\Livewire\PkoMediaLibrary;
use Pko\StorefrontCms\Models\Setting;

class StorefrontCmsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storefront-cms');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->mergeDbSettingsIntoConfig();

        Livewire::component('storefront-cms.home-hero', HomeHero::class);
        Livewire::component('storefront-cms.home-tiles', HomeTiles::class);
        Livewire::component('storefront-cms.home-offers', HomeOffers::class);
        Livewire::component('storefront-cms.home-posts', HomePosts::class);
        Livewire::component('storefront-cms.home-featured', HomeFeaturedProducts::class);
        Livewire::component('pko-media-library', PkoMediaLibrary::class);
    }

    /**
     * Override config('storefront.*') values with DB settings when present.
     * Table may not exist yet during install/migrate — guard with Schema::hasTable.
     */
    private function mergeDbSettingsIntoConfig(): void
    {
        try {
            if (! Schema::hasTable('pko_storefront_settings')) {
                return;
            }

            $rows = Setting::all(['key', 'value']);
            if ($rows->isEmpty()) {
                return;
            }

            foreach ($rows as $row) {
                if ($row->value === null || $row->value === '') {
                    continue;
                }
                config(['storefront.'.$row->key => $row->value]);
            }
        } catch (\Throwable) {
            // DB not ready yet (install/ci) — noop
        }
    }
}
