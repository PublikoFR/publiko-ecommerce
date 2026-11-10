<?php

declare(strict_types=1);

namespace Pko\Loyalty;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lunar\Base\Validation\CouponValidator;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Loyalty\Console\RecalculateLoyaltyTiersCommand;
use Pko\Loyalty\Console\ResetAnnualLoyaltyPointsCommand;
use Pko\Loyalty\DiscountTypes\LoyaltyCouponValidator;
use Pko\Loyalty\DiscountTypes\LoyaltyVoucher;
use Pko\Loyalty\Models\CustomerPoints;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\PointsHistory;
use Pko\Loyalty\Observers\OrderObserver;
use Pko\Loyalty\Observers\RefundTransactionObserver;
use Pko\Loyalty\Services\LoyaltyManager;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/loyalty.php', 'loyalty');
        $this->app->singleton(LoyaltyManager::class, fn () => new LoyaltyManager);

        // Le validateur Lunar par défaut refuse les codes d'un type de remise
        // qu'il ne connaît pas : sans ça, un bon fidélité serait rejeté au panier.
        $this->app->bind(CouponValidator::class, LoyaltyCouponValidator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'loyalty');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'loyalty');

        // Le bon d'achat fidélité se cumule toujours avec la remise client % et se
        // déduit après elle (règle de non-cumul, cf. CartDiscountArbiter). Contrat
        // par config : pas de dépendance de classe entre les deux packages.
        config(['customer-auth.stackable_discount_types' => array_values(array_unique([
            ...(array) config('customer-auth.stackable_discount_types', []),
            LoyaltyVoucher::class,
        ]))]);

        Order::observe(OrderObserver::class);
        Transaction::observe(RefundTransactionObserver::class);

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                RecalculateLoyaltyTiersCommand::class,
                ResetAnnualLoyaltyPointsCommand::class,
            ]);

            $this->app->booted(function (): void {
                // Quotidienne plutôt que yearlyOn(1, 1) : rattrape un scheduler
                // arrêté le 1er janvier, et ne fait rien les autres jours.
                $this->app->make(Schedule::class)
                    ->command(ResetAnnualLoyaltyPointsCommand::class)
                    ->dailyAt('00:05');
            });
        }
    }
}
