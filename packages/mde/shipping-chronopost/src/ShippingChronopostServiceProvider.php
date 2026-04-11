<?php

declare(strict_types=1);

namespace Mde\ShippingChronopost;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Mde\ShippingChronopost\Modifiers\ChronopostModifier;
use Mde\ShippingChronopost\Services\ChronopostClient;
use Mde\ShippingCommon\Contracts\CarrierClient;

class ShippingChronopostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chronopost.php', 'chronopost');

        $this->app->singleton('mde.shipping.carrier.chronopost', function () {
            return new ChronopostClient(config('chronopost') ?? []);
        });

        $this->app->bind(ChronopostClient::class, fn () => $this->app->make('mde.shipping.carrier.chronopost'));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mde-shipping-chronopost');

        /** @var ShippingModifiers $modifiers */
        $modifiers = $this->app->make(ShippingModifiers::class);
        $modifiers->add(ChronopostModifier::class);
    }
}
