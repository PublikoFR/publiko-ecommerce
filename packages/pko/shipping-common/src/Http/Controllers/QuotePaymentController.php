<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * Payment page for a quote-only order (status `awaiting-quote`).
 *
 * Flow:
 *  - show()    : opened from the signed e-mail link. Creates (or reuses) a Stripe
 *                PaymentIntent for `order total + transport final`, then renders the
 *                Stripe Payment Element. The transport amount comes from the signed
 *                query string so it cannot be tampered with, and is persisted to the
 *                order meta for the confirmation step.
 *  - confirm() : Stripe `return_url` after the client confirms the payment. Verifies
 *                the PaymentIntent server-side (no signature needed: the intent id is
 *                bound to the order in our DB and Stripe is the source of truth for the
 *                `succeeded` status). On success, records a Lunar transaction and moves
 *                the order to `payment-received`, which triggers OrderShipmentObserver.
 *
 * Reuses the existing lunarphp/stripe integration (no Cashier — cf. CLAUDE.md).
 */
class QuotePaymentController extends Controller
{
    /** PaymentIntent statuses for which an existing intent can be reused as-is. */
    private const REUSABLE_STATUSES = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
        'processing',
    ];

    private const PAID_STATUSES = ['paid', 'payment-received'];

    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Lien de paiement invalide ou expiré.');
        abort_if($order->status !== 'awaiting-quote', 410, 'Cette commande n\'est plus en attente de devis.');

        $transportCents = max(0, (int) $request->query('transport_cents', 0));
        $amount = (int) $order->total->value + $transportCents;

        $this->configureStripe();

        $intent = $this->resolveIntent($order, $amount, $transportCents);

        // Returning after a successful confirmation (e.g. browser back) → finalise.
        if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
            return redirect()->route('pko.quote.pay.confirm', ['order' => $order->id]);
        }

        return response()->view('pko-shipping-common::quote-payment', [
            'order' => $order,
            'transportCents' => $transportCents,
            'amount' => $amount,
            'currency' => strtoupper($order->currency_code),
            'clientSecret' => $intent->client_secret,
            'publishableKey' => (string) config('services.stripe.public_key'),
            'returnUrl' => route('pko.quote.pay.confirm', ['order' => $order->id]),
        ]);
    }

    public function confirm(Request $request, Order $order): Response
    {
        $meta = $this->quoteMeta($order);
        $intentId = $meta['intent_id'] ?? null;

        abort_if($intentId === null, 410, 'Aucun paiement en cours pour cette commande.');

        $this->configureStripe();

        try {
            $intent = PaymentIntent::retrieve($intentId);
        } catch (ApiErrorException) {
            $intent = null;
        }

        $succeeded = $intent !== null && $intent->status === PaymentIntent::STATUS_SUCCEEDED;

        if ($succeeded && $order->status === 'awaiting-quote') {
            $this->markOrderPaid($order, $intent, (int) ($meta['transport_cents'] ?? 0));
        }

        $order->refresh();

        return response()->view('pko-shipping-common::quote-payment-result', [
            'order' => $order,
            'paid' => $succeeded || in_array($order->status, self::PAID_STATUSES, true),
        ]);
    }

    /**
     * Fetch a reusable intent for the order or create a fresh one.
     */
    private function resolveIntent(Order $order, int $amount, int $transportCents): PaymentIntent
    {
        $existingId = $this->quoteMeta($order)['intent_id'] ?? null;

        if ($existingId !== null) {
            try {
                $intent = PaymentIntent::retrieve($existingId);

                if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
                    return $intent;
                }

                if (in_array($intent->status, self::REUSABLE_STATUSES, true)) {
                    if ((int) $intent->amount !== $amount) {
                        $intent = PaymentIntent::update($existingId, ['amount' => $amount]);
                    }

                    return $intent;
                }
            } catch (ApiErrorException) {
                // Intent gone / unusable → fall through and create a new one.
            }
        }

        $intent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => strtolower($order->currency_code),
            'automatic_payment_methods' => ['enabled' => true],
            'capture_method' => config('lunar.stripe.policy', 'automatic'),
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_reference' => (string) $order->reference,
                'transport_cents' => (string) $transportCents,
            ],
        ]);

        $this->storeQuoteMeta($order, [
            'intent_id' => $intent->id,
            'transport_cents' => $transportCents,
            'amount' => $amount,
        ]);

        return $intent;
    }

    /**
     * Record the Stripe charge as a Lunar transaction and move the order to a paid
     * state. Guarded inside a transaction so a refresh/double-redirect is idempotent.
     */
    private function markOrderPaid(Order $order, PaymentIntent $intent, int $transportCents): void
    {
        DB::transaction(function () use ($order, $intent, $transportCents): void {
            $order->refresh();

            if ($order->status !== 'awaiting-quote') {
                return;
            }

            Transaction::create([
                'order_id' => $order->id,
                'success' => true,
                'type' => 'capture',
                'driver' => 'stripe',
                'amount' => (int) $intent->amount,
                'reference' => $intent->id,
                'status' => $intent->status,
                'notes' => 'Paiement du devis (frais de port inclus)',
                'card_type' => 'card',          // column is NOT NULL; refined by webhook charges if needed
                'last_four' => null,
                'captured_at' => now(),
                'meta' => ['transport_cents' => $transportCents],
            ]);

            $paidStatus = config('lunar.stripe.status_mapping.'.PaymentIntent::STATUS_SUCCEEDED, 'payment-received');

            $order->update([
                'status' => $paidStatus,
                'shipping_total' => (int) $order->shipping_total->value + $transportCents,
                'total' => (int) $order->total->value + $transportCents,
                'placed_at' => $order->placed_at ?: now(),
            ]);
        });
    }

    private function configureStripe(): void
    {
        Stripe::setApiKey((string) config('services.stripe.key'));
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteMeta(Order $order): array
    {
        $meta = $order->meta?->toArray() ?? [];

        return $meta['quote_payment'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeQuoteMeta(Order $order, array $data): void
    {
        $meta = $order->meta?->toArray() ?? [];
        $meta['quote_payment'] = $data;

        $order->meta = $meta;
        $order->save();
    }
}
