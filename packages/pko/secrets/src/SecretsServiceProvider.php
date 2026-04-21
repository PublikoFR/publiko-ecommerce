<?php

declare(strict_types=1);

namespace Pko\Secrets;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Pko\Secrets\Providers\DatabaseProvider;
use Pko\Secrets\Providers\EnvProvider;
use Throwable;

class SecretsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class);
        $this->app->singleton(EnvProvider::class);
        $this->app->singleton(DatabaseProvider::class);

        $this->app->singleton(SecretStore::class, function ($app) {
            return new SecretStore(
                $app->make(Registry::class),
                $app->make(EnvProvider::class),
                $app->make(DatabaseProvider::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-secrets');

        $this->syncDatabaseSecretsToConfig();
    }

    /**
     * For every module currently in DB mode, backfill its declared config paths
     * so that third-party code reading config('services.stripe.key'), etc.,
     * transparently sees the DB-stored secret. No-op if DB is unavailable
     * (install phase, console before migrations, etc.).
     */
    protected function syncDatabaseSecretsToConfig(): void
    {
        try {
            if (! Schema::hasTable('pko_secrets') || ! Schema::hasTable('pko_storefront_settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        /** @var SecretStore $store */
        $store = $this->app->make(SecretStore::class);
        /** @var ConfigRepository $config */
        $config = $this->app->make('config');

        foreach ($store->registry()->modules() as $module) {
            if ($store->source($module) !== 'db') {
                continue;
            }

            foreach ($store->registry()->configMap($module) as $key => $path) {
                $value = $store->get($module, $key);
                if ($value !== null) {
                    $config->set($path, $value);
                }
            }
        }
    }
}
