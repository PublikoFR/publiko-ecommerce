<?php

declare(strict_types=1);

namespace Pko\Account;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Pko\Account\Livewire\AddressesPage;
use Pko\Account\Livewire\CompanyPage;
use Pko\Account\Livewire\Dashboard;
use Pko\Account\Livewire\InvoicesPage;
use Pko\Account\Livewire\LoyaltyPage;
use Pko\Account\Livewire\OrderDetailPage;
use Pko\Account\Livewire\OrdersPage;
use Pko\Account\Livewire\ProfilePage;

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
