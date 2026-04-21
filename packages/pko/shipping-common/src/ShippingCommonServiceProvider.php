<?php

declare(strict_types=1);

namespace Pko\ShippingCommon;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Order;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Observers\OrderShipmentObserver;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;

class ShippingCommonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CarrierRegistry::class);
        $this->app->singleton(CarrierGridRepository::class);
        $this->app->singleton(CarrierServiceRepository::class);
        $this->app->singleton(PricingModeResolver::class);
        $this->app->singleton(LivePricingResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-shipping-common');

        Order::observe(OrderShipmentObserver::class);
    }
}
