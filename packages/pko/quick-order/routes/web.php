<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\QuickOrder\Livewire\QuickOrderPage;

Route::middleware(['web', 'auth', 'pro.customer'])
    ->get('/achat-rapide', QuickOrderPage::class)
    ->name('quick-order');
