<?php

declare(strict_types=1);

namespace Pko\OrderNotifications;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Order;
use Pko\OrderNotifications\Console\SendAbandonedCartMailsCommand;
use Pko\OrderNotifications\Console\SendLoyaltyAnniversaryMailsCommand;
use Pko\OrderNotifications\Console\SendQuoteRemindersCommand;
use Pko\OrderNotifications\Console\SendReviewRequestsCommand;
use Pko\OrderNotifications\Observers\OrderMailObserver;

class OrderNotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/order-notifications.php', 'order-notifications');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/order-notifications.php' => config_path('order-notifications.php'),
        ], 'order-notifications-config');

        Order::observe(OrderMailObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendAbandonedCartMailsCommand::class,
                SendQuoteRemindersCommand::class,
                SendReviewRequestsCommand::class,
                SendLoyaltyAnniversaryMailsCommand::class,
            ]);

            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);

                // Horaires ouvrés et décalés : ces e-mails sont commerciaux, pas
                // transactionnels — les envoyer en pleine nuit dessert le message.
                $schedule->command(SendAbandonedCartMailsCommand::class)->dailyAt('10:00');
                $schedule->command(SendQuoteRemindersCommand::class)->dailyAt('10:30');
                $schedule->command(SendReviewRequestsCommand::class)->dailyAt('11:00');
                $schedule->command(SendLoyaltyAnniversaryMailsCommand::class)->dailyAt('09:00');
            });
        }
    }
}
