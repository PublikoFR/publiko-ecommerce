<?php

declare(strict_types=1);

namespace App\Support\Payments;

use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Stripe\Managers\StripeManager;
use Lunar\Stripe\Models\StripePaymentIntent;

/**
 * Empêche la réutilisation d'un PaymentIntent déjà consommé.
 *
 * Le manager Lunar sélectionne l'intent du panier via `paymentIntents()->active()`,
 * qui filtre sur le statut stocké *en base locale*. Ce statut n'est mis à jour que
 * par le webhook Stripe : sans webhook (ou s'il n'arrive pas), un intent passé à
 * `succeeded` chez Stripe reste `requires_payment_method` chez nous et continue
 * d'être servi au checkout. Stripe rejette alors la session Elements avec
 * « This PaymentIntent is in a terminal state » (400) et le formulaire de carte ne
 * se monte jamais — l'acheteur ne voit qu'un bouton « Payer » inerte.
 *
 * On vérifie donc l'état réel côté Stripe avant de réutiliser un intent, et on
 * resynchronise la base au passage. Un intent terminal est ignoré, ce qui fait
 * repartir `createIntent()` sur un intent neuf.
 */
final class ResilientStripeManager extends StripeManager
{
    /**
     * Mémoïsation par panier : `getCartIntentId()` est appelé plusieurs fois par
     * requête (createIntent, syncIntent, updateIntent…) et chaque résolution
     * coûterait sinon un aller-retour API.
     *
     * @var array<int, string|null>
     */
    private array $resolved = [];

    public function getCartIntentId(CartContract $cart): ?string
    {
        if (array_key_exists($cart->id, $this->resolved)) {
            return $this->resolved[$cart->id];
        }

        return $this->resolved[$cart->id] = $this->resolveUsableIntentId($cart);
    }

    private function resolveUsableIntentId(CartContract $cart): ?string
    {
        $intentId = $cart->paymentIntents()->active()->first()?->intent_id
            ?? ($cart->meta['payment_intent'] ?? null);

        if (! $intentId) {
            return null;
        }

        $intent = $this->fetchIntent((string) $intentId);

        // Intent inconnu de Stripe (clé d'API changée, compte différent) : on repart
        // sur un intent neuf plutôt que de propager une référence morte.
        if (! $intent) {
            $this->markLocalStatus($cart, (string) $intentId, StripePaymentIntent::FINAL_STATES[0]);

            return null;
        }

        if (in_array($intent->status, StripePaymentIntent::FINAL_STATES, true)) {
            $this->markLocalStatus($cart, (string) $intentId, $intent->status);

            return null;
        }

        return (string) $intentId;
    }

    private function markLocalStatus(CartContract $cart, string $intentId, string $status): void
    {
        $cart->paymentIntents()
            ->where('intent_id', $intentId)
            ->update(['status' => $status]);
    }
}
