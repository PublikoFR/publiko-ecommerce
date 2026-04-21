<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Pko\ShippingColissimo\Filament\Pages\ColissimoConfig;
use Pko\ShippingColissimo\Modifiers\ColissimoModifier;
use Pko\ShippingColissimo\Services\ColissimoClient;
use Pko\ShippingCommon\Carriers\CarrierDefinition;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Throwable;

class ShippingColissimoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/colissimo.php', 'colissimo');

        $this->app->singleton('pko.shipping.carrier.colissimo', function ($app) {
            return new ColissimoClient($this->buildClientConfig($app));
        });

        $this->app->bind(ColissimoClient::class, fn () => $this->app->make('pko.shipping.carrier.colissimo'));

        $this->app->afterResolving(CarrierRegistry::class, function (CarrierRegistry $registry): void {
            if ($registry->has('colissimo')) {
                return;
            }
            $registry->register(new CarrierDefinition(
                code: 'colissimo',
                displayName: 'Colissimo',
                icon: 'heroicon-o-truck',
                clientServiceId: 'pko.shipping.carrier.colissimo',
                secretsModule: 'colissimo',
                credentialLabels: [
                    'contract_number' => 'Numéro de contrat',
                    'password' => 'Mot de passe',
                ],
                configPageClass: ColissimoConfig::class,
                navigationSort: 21,
                meta: ['max_weight_kg' => 30],
            ));
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-shipping-colissimo');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-shipping-colissimo');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/pko-shipping-colissimo'),
        ], 'pko-shipping-colissimo-lang');

        /** @var ShippingModifiers $modifiers */
        $modifiers = $this->app->make(ShippingModifiers::class);
        $modifiers->add(ColissimoModifier::class);

        $this->app->make(CarrierRegistry::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClientConfig($app): array
    {
        $config = (array) config('colissimo', []);

        try {
            /** @var CarrierServiceRepository $services */
            $services = $app->make(CarrierServiceRepository::class);
            /** @var CarrierGridRepository $grids */
            $grids = $app->make(CarrierGridRepository::class);

            $dbServices = $services->allFor('colissimo');
            $dbGrid = $grids->forCarrier('colissimo');

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
            // DB not available: keep config defaults.
        }

        return $config;
    }
}
