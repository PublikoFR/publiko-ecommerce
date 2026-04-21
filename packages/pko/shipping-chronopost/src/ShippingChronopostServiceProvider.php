<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Pko\ShippingChronopost\Filament\Pages\ChronopostConfig;
use Pko\ShippingChronopost\Modifiers\ChronopostModifier;
use Pko\ShippingChronopost\Services\ChronopostClient;
use Pko\ShippingChronopost\Services\QuickCostSoapClient;
use Pko\ShippingCommon\Carriers\CarrierDefinition;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Throwable;

class ShippingChronopostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chronopost.php', 'chronopost');

        $this->app->singleton(QuickCostSoapClient::class, function () {
            return new QuickCostSoapClient([
                'account' => secret('chronopost.account') ?? config('chronopost.credentials.account'),
                'password' => secret('chronopost.password') ?? config('chronopost.credentials.password'),
                'sub_account' => secret('chronopost.sub_account') ?? config('chronopost.credentials.sub_account'),
            ]);
        });

        $this->app->singleton('pko.shipping.carrier.chronopost', function ($app) {
            return new ChronopostClient(
                config: $this->buildClientConfig($app),
                livePricing: $app->make(LivePricingResolver::class),
                modes: $app->make(PricingModeResolver::class),
                liveClient: $app->make(QuickCostSoapClient::class),
            );
        });

        $this->app->bind(ChronopostClient::class, fn () => $this->app->make('pko.shipping.carrier.chronopost'));

        // Register the carrier synchronously so TransportersPlugin (which runs during
        // Filament's panel registration in boot phase) sees it.
        $this->app->afterResolving(CarrierRegistry::class, function (CarrierRegistry $registry): void {
            if ($registry->has('chronopost')) {
                return;
            }
            $registry->register(new CarrierDefinition(
                code: 'chronopost',
                displayName: 'Chronopost',
                icon: 'heroicon-o-truck',
                clientServiceId: 'pko.shipping.carrier.chronopost',
                secretsModule: 'chronopost',
                credentialLabels: [
                    'account' => 'Numéro de compte',
                    'password' => 'Mot de passe',
                    'sub_account' => 'Sous-compte (optionnel)',
                ],
                configPageClass: ChronopostConfig::class,
                navigationSort: 20,
                meta: ['max_weight_kg' => 30],
                supportsLive: true,
            ));
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-shipping-chronopost');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-shipping-chronopost');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/pko-shipping-chronopost'),
        ], 'pko-shipping-chronopost-lang');

        /** @var ShippingModifiers $modifiers */
        $modifiers = $this->app->make(ShippingModifiers::class);
        $modifiers->add(ChronopostModifier::class);

        // Ensure the carrier is registered even if afterResolving did not fire
        // (e.g. Registry resolved earlier than expected).
        $this->app->make(CarrierRegistry::class);
    }

    /**
     * Build the array config passed to the Client.
     *
     * Credentials come from config() (backfilled by SecretsServiceProvider when
     * in DB mode). Services and grid brackets come from the DB repositories when
     * available, with a transparent fallback to the historical config arrays so
     * pure-unit tests (that don't boot the framework) keep working.
     *
     * @return array<string, mixed>
     */
    protected function buildClientConfig($app): array
    {
        $config = (array) config('chronopost', []);

        try {
            /** @var CarrierServiceRepository $services */
            $services = $app->make(CarrierServiceRepository::class);
            /** @var CarrierGridRepository $grids */
            $grids = $app->make(CarrierGridRepository::class);

            $dbServices = $services->allFor('chronopost');
            $dbGrid = $grids->forCarrier('chronopost');

            if ($dbServices !== []) {
                $mapped = [];
                foreach ($dbServices as $s) {
                    $mapped[$s['code']] = ['label' => $s['label'], 'enabled' => $s['enabled']];
                }
                $config['services'] = $mapped;
            }

            if ($dbGrid !== []) {
                $config['grid'] = array_map(
                    fn (array $b): array => ['max_kg' => $b['max_kg'], 'price' => $b['price']],
                    $dbGrid,
                );
            }
        } catch (Throwable) {
            // DB not available (install phase, pure unit tests): keep config defaults.
        }

        return $config;
    }
}
