<?php

declare(strict_types=1);

namespace Mde\ShippingCommon;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Order;
use Mde\ShippingCommon\Observers\OrderShipmentObserver;

class ShippingCommonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Order::observe(OrderShipmentObserver::class);
    }
}
