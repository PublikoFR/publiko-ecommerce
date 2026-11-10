<?php

declare(strict_types=1);

namespace Pko\CustomerAuth;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Console\BackfillDefaultCustomerGroupCommand;
use Pko\CustomerAuth\Http\Middleware\RedirectIfProCustomer;
use Pko\CustomerAuth\Http\Middleware\RequireProCustomer;
use Pko\CustomerAuth\Livewire\ForgotPasswordPage;
use Pko\CustomerAuth\Livewire\LoginPage;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Livewire\ResetPasswordPage;
use Pko\CustomerAuth\Models\CustomerDiscount;
use Pko\CustomerAuth\Models\NegotiatedPrice;
use Pko\CustomerAuth\Observers\CustomerActivationObserver;
use Pko\CustomerAuth\Observers\CustomerGroupHandleObserver;
use Pko\CustomerAuth\Pricing\CustomerDiscountResolver;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Support\CustomerDiscountTargets;

class CustomerAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/customer-auth.php', 'customer-auth');

        $this->app->singleton(SireneClient::class, fn () => new SireneClient(
            baseUrl: (string) config('customer-auth.sirene.base_url'),
            apiKey: (string) config('customer-auth.sirene.api_key'),
            enabled: (bool) config('customer-auth.sirene.enabled'),
            timeout: (int) config('customer-auth.sirene.timeout'),
        ));

        // Une instance par requête / job : mémorise les remises du client et les
        // cibles produit (pas de N+1 sur les listings), sans fuite entre requêtes.
        $this->app->scoped(CustomerDiscountResolver::class);

        // Libellés des cibles de remise (back-office), chargés une fois par requête.
        $this->app->scoped(CustomerDiscountTargets::class);
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'customer-auth');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $router->aliasMiddleware('pro.customer', RequireProCustomer::class);
        $router->aliasMiddleware('redirect.if.pro', RedirectIfProCustomer::class);

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillDefaultCustomerGroupCommand::class]);
        }

        // Le handle d'un groupe client doit rester un slug : l'inscription et le
        // contrôle d'accès pro résolvent le groupe par défaut par handle, et le
        // champ est librement éditable dans l'admin Lunar.
        CustomerGroup::observe(CustomerGroupHandleObserver::class);

        // E-mail « compte validé » au passage pending → active (validation manuelle
        // en back-office, quel que soit le chemin : action ou Select de la fiche).
        Customer::observe(CustomerActivationObserver::class);

        // Relation « prix négociés » ajoutée au modèle Customer de Lunar sans le
        // subclasser (utilisée par le RelationManager Filament + le pipeline pricing).
        Customer::resolveRelationUsing(
            'negotiatedPrices',
            fn (Customer $customer) => $customer->hasMany(NegotiatedPrice::class, 'customer_id'),
        );

        // Remises client % (collection / famille fabricant / marque).
        Customer::resolveRelationUsing(
            'customerDiscounts',
            fn (Customer $customer) => $customer->hasMany(CustomerDiscount::class, 'customer_id'),
        );

        Livewire::component('customer-auth.login', LoginPage::class);
        Livewire::component('customer-auth.register', RegisterPage::class);
        Livewire::component('customer-auth.forgot-password', ForgotPasswordPage::class);
        Livewire::component('customer-auth.reset-password', ResetPasswordPage::class);
    }
}
