<?php

declare(strict_types=1);

namespace Pko\Pennylane;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Api\Resources\CustomersResource;
use Pko\Pennylane\Console\Commands\PennylaneBackfillCommand;
use Pko\Pennylane\Console\Commands\PennylanePollChangelogCommand;
use Pko\Pennylane\Console\Commands\PennylaneResyncOrderCommand;
use Pko\Pennylane\Observers\OrderPennylaneObserver;
use Pko\Pennylane\Observers\TransactionPennylaneObserver;
use Pko\Pennylane\Services\CreditNoteSynchronizer;
use Pko\Pennylane\Services\CustomerMapper;
use Pko\Pennylane\Services\InvoiceSynchronizer;
use Pko\Pennylane\Services\OrderToInvoiceMapper;
use Pko\Pennylane\Services\TransactionToCreditNoteMapper;

final class PennylaneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pennylane.php', 'pennylane');

        $this->app->singleton(PennylaneClient::class, function (Application $app): PennylaneClient {
            return new PennylaneClient(
                http: $app->make(HttpFactory::class),
                config: $app['config']->get('pennylane'),
            );
        });

        $this->app->singleton(CustomerInvoicesResource::class);
        $this->app->singleton(CustomersResource::class);

        $this->app->singleton(OrderToInvoiceMapper::class);
        $this->app->singleton(TransactionToCreditNoteMapper::class);
        $this->app->singleton(CustomerMapper::class);
        $this->app->singleton(InvoiceSynchronizer::class);
        $this->app->singleton(CreditNoteSynchronizer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-pennylane');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-pennylane');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');

        $this->publishes([
            __DIR__.'/../config/pennylane.php' => config_path('pennylane.php'),
        ], 'pennylane-config');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/pko-pennylane'),
        ], 'pennylane-lang');

        Order::observe(OrderPennylaneObserver::class);
        Transaction::observe(TransactionPennylaneObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PennylaneResyncOrderCommand::class,
                PennylaneBackfillCommand::class,
                PennylanePollChangelogCommand::class,
            ]);

            $this->app->booted(function (): void {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('pennylane:poll-changelog')
                    ->everyFifteenMinutes()
                    ->withoutOverlapping()
                    ->runInBackground();
            });
        }
    }
}
