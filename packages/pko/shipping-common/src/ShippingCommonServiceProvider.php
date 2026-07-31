<?php

declare(strict_types=1);

namespace Pko\ShippingCommon;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\Base\ShippingModifiers;
use Lunar\Models\Order;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Console\Commands\PollTrackingCommand;
use Pko\ShippingCommon\Contracts\PickupPointProvider;
use Pko\ShippingCommon\Filament\Livewire\CarrierGridTable;
use Pko\ShippingCommon\Filament\Livewire\CarrierServicesTable;
use Pko\ShippingCommon\Models\CarrierGridBracket;
use Pko\ShippingCommon\Models\CarrierService;
use Pko\ShippingCommon\Modifiers\UnifiedShippingModifier;
use Pko\ShippingCommon\Observers\OrderShipmentObserver;
use Pko\ShippingCommon\Pickup\ManualPickupPointProvider;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Pricing\ShippingCalculator;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;

class ShippingCommonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shipping.php', 'shipping');

        $this->app->singleton(CarrierRegistry::class);
        $this->app->singleton(CarrierGridRepository::class);
        $this->app->singleton(CarrierServiceRepository::class);
        $this->app->singleton(PricingModeResolver::class);
        $this->app->singleton(LivePricingResolver::class);
        $this->app->singleton(ShippingCalculator::class);

        // Provider de points relais — V1 manuel par défaut. Un adapter API
        // (SOAP Chronopost) peut être lié à la place sans toucher au front.
        $this->app->bind(PickupPointProvider::class, ManualPickupPointProvider::class);

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
        // Route de la page de paiement des commandes sur devis (URL signée générée
        // par OrderQuoteActionsExtension). Chargée par le package lui-même pour
        // l'isolation/auto-discovery (cf. CLAUDE.md §3.2), pas par AppServiceProvider.
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-shipping-common');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/pko-shipping-common')], 'pko-shipping-common-lang');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PollTrackingCommand::class,
            ]);
        }

        Order::observe(OrderShipmentObserver::class);

        // Tables CRUD embarquées dans la page de configuration transporteur
        // (une page Filament ne peut héberger qu'une seule table).
        Livewire::component('pko-shipping.carrier-services-table', CarrierServicesTable::class);
        Livewire::component('pko-shipping.carrier-grid-table', CarrierGridTable::class);

        // Toute écriture sur les services/paliers (admin, migration, seeder,
        // import de tarifs publics) invalide le cache du repository concerné.
        $flushServices = function (CarrierService $service): void {
            $this->app->make(CarrierServiceRepository::class)->flushCache((string) $service->carrier_code);
        };
        CarrierService::saved($flushServices);
        CarrierService::deleted($flushServices);

        $flushGrid = function (CarrierGridBracket $bracket): void {
            $this->app->make(CarrierGridRepository::class)->flushCache((string) $bracket->carrier_code);
        };
        CarrierGridBracket::saved($flushGrid);
        CarrierGridBracket::deleted($flushGrid);

        /** @var ShippingModifiers $modifiers */
        $modifiers = $this->app->make(ShippingModifiers::class);
        // Un seul modifier Lunar — toute la logique est dans ShippingCalculator.
        $modifiers->add(UnifiedShippingModifier::class);
    }
}
