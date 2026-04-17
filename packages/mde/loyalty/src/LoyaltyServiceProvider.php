<?php

declare(strict_types=1);

namespace Mde\Loyalty;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Mde\Loyalty\Models\CustomerPoints;
use Mde\Loyalty\Models\GiftHistory;
use Mde\Loyalty\Models\PointsHistory;
use Mde\Loyalty\Observers\OrderObserver;
use Mde\Loyalty\Services\LoyaltyManager;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mde-loyalty.php', 'mde-loyalty');
        $this->app->singleton(LoyaltyManager::class, fn () => new LoyaltyManager);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mde-loyalty');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mde-loyalty');

        Order::observe(OrderObserver::class);

        Customer::resolveRelationUsing(
            'loyaltyPoints',
            fn (Customer $customer) => $customer->hasOne(CustomerPoints::class, 'customer_id'),
        );

        Customer::resolveRelationUsing(
            'giftHistory',
            fn (Customer $customer) => $customer->hasMany(GiftHistory::class, 'customer_id'),
        );

        Customer::resolveRelationUsing(
            'pointsHistory',
            fn (Customer $customer) => $customer->hasMany(PointsHistory::class, 'customer_id'),
        );
    }
}
