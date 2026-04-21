<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Pko\ShippingColissimo\Modifiers\ColissimoModifier;
use Pko\ShippingColissimo\Services\ColissimoClient;

class ShippingColissimoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/colissimo.php', 'colissimo');

        $this->app->singleton('pko.shipping.carrier.colissimo', function () {
            return new ColissimoClient(config('colissimo') ?? []);
        });

        $this->app->bind(ColissimoClient::class, fn () => $this->app->make('pko.shipping.carrier.colissimo'));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mde-shipping-colissimo');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-shipping-colissimo');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/pko-shipping-colissimo'),
        ], 'pko-shipping-colissimo-lang');

        /** @var ShippingModifiers $modifiers */
        $modifiers = $this->app->make(ShippingModifiers::class);
        $modifiers->add(ColissimoModifier::class);
    }
}
