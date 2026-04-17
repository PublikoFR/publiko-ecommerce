<?php

declare(strict_types=1);

namespace Mde\Account;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mde\Account\Livewire\AddressesPage;
use Mde\Account\Livewire\CompanyPage;
use Mde\Account\Livewire\Dashboard;
use Mde\Account\Livewire\InvoicesPage;
use Mde\Account\Livewire\LoyaltyPage;
use Mde\Account\Livewire\OrderDetailPage;
use Mde\Account\Livewire\OrdersPage;
use Mde\Account\Livewire\ProfilePage;

class AccountServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'account');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Livewire::component('account.dashboard', Dashboard::class);
        Livewire::component('account.profile', ProfilePage::class);
        Livewire::component('account.company', CompanyPage::class);
        Livewire::component('account.addresses', AddressesPage::class);
        Livewire::component('account.orders', OrdersPage::class);
        Livewire::component('account.order-detail', OrderDetailPage::class);
        Livewire::component('account.loyalty', LoyaltyPage::class);
        Livewire::component('account.invoices', InvoicesPage::class);
    }
}
