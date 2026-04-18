<?php

declare(strict_types=1);

namespace Pko\CustomerAuth;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Pko\CustomerAuth\Http\Middleware\RedirectIfProCustomer;
use Pko\CustomerAuth\Http\Middleware\RequireProCustomer;
use Pko\CustomerAuth\Livewire\ForgotPasswordPage;
use Pko\CustomerAuth\Livewire\LoginPage;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Livewire\ResetPasswordPage;
use Pko\CustomerAuth\Sirene\SireneClient;

class CustomerAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/customer-auth.php', 'customer-auth');

        $this->app->singleton(SireneClient::class, fn () => new SireneClient(
            baseUrl: (string) config('customer-auth.sirene.base_url'),
            consumerKey: (string) config('customer-auth.sirene.consumer_key'),
            consumerSecret: (string) config('customer-auth.sirene.consumer_secret'),
            enabled: (bool) config('customer-auth.sirene.enabled'),
            timeout: (int) config('customer-auth.sirene.timeout'),
        ));
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'customer-auth');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $router->aliasMiddleware('pro.customer', RequireProCustomer::class);
        $router->aliasMiddleware('redirect.if.pro', RedirectIfProCustomer::class);

        Livewire::component('customer-auth.login', LoginPage::class);
        Livewire::component('customer-auth.register', RegisterPage::class);
        Livewire::component('customer-auth.forgot-password', ForgotPasswordPage::class);
        Livewire::component('customer-auth.reset-password', ResetPasswordPage::class);
    }
}
