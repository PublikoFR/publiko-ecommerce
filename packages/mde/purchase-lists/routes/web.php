<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mde\PurchaseLists\Livewire\PurchaseListPage;
use Mde\PurchaseLists\Livewire\PurchaseListsPage;

Route::middleware(['web', 'auth', 'pro.customer'])
    ->prefix('compte/listes-achat')
    ->name('account.purchase-lists.')
    ->group(function () {
        Route::get('/', PurchaseListsPage::class)->name('index');
        Route::get('/{list}', PurchaseListPage::class)->name('show');
    });
