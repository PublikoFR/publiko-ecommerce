<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\ShippingCommon\Http\Controllers\QuotePaymentController;
use Pko\ShippingCommon\Http\Controllers\ShowCarrierLabelController;

// `web` group is required so SubstituteBindings resolves the {order} model
// (loadRoutesFrom registers routes without a middleware group otherwise).
Route::middleware('web')->group(function (): void {
    // Signed link sent to the customer (transport price tamper-proof in the signature).
    Route::get('/paiement-devis/{order}', [QuotePaymentController::class, 'show'])
        ->name('pko.quote.pay')
        ->middleware('signed');

    // Stripe return_url after payment confirmation. Unsigned on purpose: Stripe appends
    // its own query params (payment_intent, redirect_status…) which would break the
    // signature. The intent is verified server-side against the order instead.
    Route::get('/paiement-devis/{order}/confirmation', [QuotePaymentController::class, 'confirm'])
        ->name('pko.quote.pay.confirm');
});

// Étiquette transporteur affichée dans le navigateur. Double garde, comme les PDF
// Pennylane : session staff + URL signée temporaire (CarrierLabelUrl).
Route::middleware(['web', 'auth:staff'])
    ->get('/admin/expedition/etiquettes/{shipment}/pdf', ShowCarrierLabelController::class)
    ->whereNumber('shipment')
    ->name('pko.shipping.label.pdf');
