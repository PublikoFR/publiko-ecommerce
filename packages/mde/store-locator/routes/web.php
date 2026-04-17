<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mde\StoreLocator\Http\Controllers\StoreController;

Route::middleware('web')->group(function () {
    Route::get('/magasins', [StoreController::class, 'index'])->name('stores.index');
    Route::get('/magasins/{slug}', [StoreController::class, 'show'])->name('stores.show');
});
