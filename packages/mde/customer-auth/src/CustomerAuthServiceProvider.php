<?php

declare(strict_types=1);

namespace Mde\CustomerAuth;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mde\CustomerAuth\Http\Middleware\RedirectIfProCustomer;
use Mde\CustomerAuth\Http\Middleware\RequireProCustomer;
use Mde\CustomerAuth\Livewire\ForgotPasswordPage;
use Mde\CustomerAuth\Livewire\LoginPage;
use Mde\CustomerAuth\Livewire\RegisterPage;
use Mde\CustomerAuth\Livewire\ResetPasswordPage;
use Mde\CustomerAuth\Sirene\SireneClient;

class CustomerAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mde-customer-auth.php', 'mde-customer-auth');

        $this->app->singleton(SireneClient::class, fn () => new SireneClient(
            baseUrl: (string) config('mde-customer-auth.sirene.base_url'),
            consumerKey: (string) config('mde-customer-auth.sirene.consumer_key'),
            consumerSecret: (string) config('mde-customer-auth.sirene.consumer_secret'),
            enabled: (bool) config('mde-customer-auth.sirene.enabled'),
            timeout: (int) config('mde-customer-auth.sirene.timeout'),
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
