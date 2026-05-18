<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\Pennylane\Http\Controllers\DownloadPennylanePdfController;

Route::middleware(['web', 'auth'])
    ->prefix('admin/pennylane')
    ->name('pennylane.')
    ->group(function (): void {
        Route::get('invoice/{order}/pdf', [DownloadPennylanePdfController::class, 'invoice'])
            ->whereNumber('order')
            ->name('invoice.pdf');

        Route::get('credit-note/{transaction}/pdf', [DownloadPennylanePdfController::class, 'creditNote'])
            ->whereNumber('transaction')
            ->name('credit-note.pdf');
    });
