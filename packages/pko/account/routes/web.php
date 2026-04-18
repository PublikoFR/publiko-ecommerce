<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\Account\Livewire\AddressesPage;
use Pko\Account\Livewire\CompanyPage;
use Pko\Account\Livewire\Dashboard;
use Pko\Account\Livewire\InvoicesPage;
use Pko\Account\Livewire\LoyaltyPage;
use Pko\Account\Livewire\OrderDetailPage;
use Pko\Account\Livewire\OrdersPage;
use Pko\Account\Livewire\ProfilePage;

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
