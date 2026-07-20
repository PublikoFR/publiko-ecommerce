<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Models\Contracts\Cart as CartContract;
use Stripe\PaymentIntent;
use Stripe\Stripe as StripeClient;

class SepaPaymentForm extends Component
{
    public CartContract $cart;

    public string $returnUrl = '';

    public function mount(): void
    {
        StripeClient::setApiKey(config('services.stripe.key'));
    }

    /**
     * Fetch or create a SEPA-specific PaymentIntent for the cart.
     * Uses cart meta 'sepa_intent_id' to avoid reusing the card intent.
     */
    public function getClientSecretProperty(): string
    {
        StripeClient::setApiKey(config('services.stripe.key'));

        $existingId = $this->cart->meta['sepa_intent_id'] ?? null;

        if ($existingId) {
            $intent = PaymentIntent::retrieve($existingId);
            if (in_array($intent->status, [
                PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
                PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
                PaymentIntent::STATUS_REQUIRES_ACTION,
            ], true)) {
                return $intent->client_secret;
            }
        }

        $intent = PaymentIntent::create([
            'amount' => $this->cart->total->value,
            'currency' => $this->cart->currency->code,
            'payment_method_types' => ['sepa_debit'],
            'capture_method' => 'automatic',
        ]);

        // Track in the Lunar stripe_payment_intents table (needed by authorize())
        $this->cart->paymentIntents()->create([
            'intent_id' => $intent->id,
            'status' => $intent->status,
        ]);

        // Store the SEPA intent ID separately to avoid conflicts with card intent
        $meta = $this->cart->meta ?? [];
        $meta['sepa_intent_id'] = $intent->id;
        $this->cart->update(['meta' => $meta]);

        return $intent->client_secret;
    }

    public function getBillingProperty(): mixed
    {
        return $this->cart->billingAddress;
    }

    public function render(): View
    {
        return view('livewire.sepa-payment-form');
    }
}
