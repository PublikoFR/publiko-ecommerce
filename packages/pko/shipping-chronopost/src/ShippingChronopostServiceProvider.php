<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Pko\ShippingChronopost\Modifiers\ChronopostModifier;
use Pko\ShippingChronopost\Services\ChronopostClient;

class ShippingChronopostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chronopost.php', 'chronopost');

        $this->app->singleton('pko.shipping.carrier.chronopost', function () {
            return new ChronopostClient(config('chronopost') ?? []);
        });

        $this->app->bind(ChronopostClient::class, fn () => $this->app->make('pko.shipping.carrier.chronopost'));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mde-shipping-chronopost');

        /** @var ShippingModifiers $modifiers */
        $modifiers = $this->app->make(ShippingModifiers::class);
        $modifiers->add(ChronopostModifier::class);
    }
}
