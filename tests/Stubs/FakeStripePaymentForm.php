<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Livewire\Component;

/**
 * Remplaçant du composant Livewire `stripe.payment` (Lunar\Stripe\Components\PaymentForm)
 * pour les tests qui rendent la page de checkout.
 *
 * Le composant réel appelle Stripe::createIntent() **au rendu** : sans clé d'API il
 * lève une ViewException, et avec une clé factice il déroule tout le SDK Stripe
 * (segfault PHP intermittent observé sur le chunk Shipping). Le formulaire de
 * paiement n'est pas le sujet de ces tests : on le remplace par un stub inerte.
 */
class FakeStripePaymentForm extends Component
{
    public mixed $cart = null;

    public ?string $returnUrl = null;

    public function render(): string
    {
        return '<div data-testid="stripe-payment-stub"></div>';
    }
}
