<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mde\Account\Livewire\AddressesPage;
use Mde\Account\Livewire\CompanyPage;
use Mde\Account\Livewire\Dashboard;
use Mde\Account\Livewire\InvoicesPage;
use Mde\Account\Livewire\LoyaltyPage;
use Mde\Account\Livewire\OrderDetailPage;
use Mde\Account\Livewire\OrdersPage;
use Mde\Account\Livewire\ProfilePage;

Route::middleware(['web', 'auth', 'pro.customer'])
    ->prefix('compte')
    ->name('account.')
    ->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('/profil', ProfilePage::class)->name('profile');
        Route::get('/societe', CompanyPage::class)->name('company');
        Route::get('/adresses', AddressesPage::class)->name('addresses');
        Route::get('/commandes', OrdersPage::class)->name('orders');
        Route::get('/commandes/{order}', OrderDetailPage::class)->name('order.view');
        Route::get('/fidelite', LoyaltyPage::class)->name('loyalty');
        Route::get('/factures', InvoicesPage::class)->name('invoices');
    });
