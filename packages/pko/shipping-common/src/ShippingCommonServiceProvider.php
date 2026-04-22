<?php

declare(strict_types=1);

namespace Pko\ShippingCommon;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Order;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Console\Commands\PollTrackingCommand;
use Pko\ShippingCommon\Observers\OrderShipmentObserver;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;

class ShippingCommonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CarrierRegistry::class);
        $this->app->singleton(CarrierGridRepository::class);
        $this->app->singleton(CarrierServiceRepository::class);
        $this->app->singleton(PricingModeResolver::class);
        $this->app->singleton(LivePricingResolver::class);

        $this->app->singleton(LaPosteTrackingClient::class, function ($app) {
            return new LaPosteTrackingClient(
                http: $app->make(Factory::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-shipping-common');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PollTrackingCommand::class,
            ]);
        }

        Order::observe(OrderShipmentObserver::class);
    }
}
