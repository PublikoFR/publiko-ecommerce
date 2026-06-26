<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\ShippingCommon\Http\Controllers\QuotePaymentController;

Route::get('/paiement-devis/{order}', QuotePaymentController::class)
    ->name('pko.quote.pay')
    ->middleware(['signed']);
