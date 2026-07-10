<?php

declare(strict_types=1);

namespace Pko\Storefront;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\Models\Collection;
use Lunar\Models\Url;
use Pko\Storefront\Livewire\CartDrawer;
use Pko\Storefront\Livewire\SearchAutocomplete;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StorefrontServiceProvider extends ServiceProvider
{
    /** Clé de cache de l'arbre de navigation (menu latéral catégories). */
    public const NAV_CACHE_KEY = 'pko.storefront.nav.roots.v3';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'storefront');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storefront');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components');

        Livewire::component('storefront.search-autocomplete', SearchAutocomplete::class);
        Livewire::component('storefront.cart-drawer', CartDrawer::class);

        $this->registerNavCacheInvalidation();

        $this->publishes([
            __DIR__.'/../config/storefront.php' => config_path('storefront.php'),
        ], 'storefront-config');
    }

    /**
     * Invalide le cache du menu dès qu'une catégorie (ou son URL) change,
     * pour que la navigation reflète les modifications sans rebuild ni délai.
     */
    private function registerNavCacheInvalidation(): void
    {
        $flush = fn () => Cache::forget(self::NAV_CACHE_KEY);

        foreach (['saved', 'deleted', 'restored'] as $event) {
            Collection::registerModelEvent($event, $flush);
            Url::registerModelEvent($event, $flush);
        }

        // Changement d'image d'une catégorie (média Spatie attaché à une Collection).
        foreach (['saved', 'deleted'] as $event) {
            Media::registerModelEvent($event, function (Media $media) use ($flush): void {
                $model = Relation::getMorphedModel($media->model_type) ?? $media->model_type;

                if (is_a($model, Collection::class, true)) {
                    $flush();
                }
            });
        }
    }
}
