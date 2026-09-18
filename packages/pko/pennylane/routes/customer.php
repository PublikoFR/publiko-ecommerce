<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\Pennylane\Http\Controllers\DownloadPennylanePdfController;

Route::middleware(['web', 'auth', 'pro.customer'])
    ->get('compte/factures/{invoice}/pdf', [DownloadPennylanePdfController::class, 'customer'])
    ->whereNumber('invoice')
    ->name('pennylane.customer.pdf');
