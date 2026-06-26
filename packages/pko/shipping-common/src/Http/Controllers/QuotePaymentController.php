<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Lunar\Models\Order;

/**
 * Displays the payment page for a quote-only order.
 * The URL is signed (URL::signedRoute) so the transport price cannot be tampered with.
 *
 * TODO: replace the stub view with a real Stripe/payment integration once the
 * checkout flow supports transport-price injection from a signed URL.
 */
class QuotePaymentController extends Controller
{
    public function __invoke(Request $request, Order $order): Response
    {
        abort_unless($request->hasValidSignature(), 403, 'Lien de paiement invalide ou expiré.');

        $transportCents = (int) $request->query('transport_cents', 0);

        return response()->view('pko-shipping-common::quote-payment', [
            'order' => $order,
            'transportCents' => $transportCents,
        ]);
    }
}
