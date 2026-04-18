<?php

declare(strict_types=1);

namespace Pko\Loyalty;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Pko\Loyalty\Models\CustomerPoints;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\PointsHistory;
use Pko\Loyalty\Observers\OrderObserver;
use Pko\Loyalty\Services\LoyaltyManager;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/loyalty.php', 'loyalty');
        $this->app->singleton(LoyaltyManager::class, fn () => new LoyaltyManager);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'loyalty');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'loyalty');

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
