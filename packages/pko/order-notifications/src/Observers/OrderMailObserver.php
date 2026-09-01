<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Observers;

use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\MailTemplates\Support\OnceMailer;
use Pko\OrderNotifications\Mail\FirstOrderGiftMail;
use Pko\OrderNotifications\Mail\OrderConfirmedMail;
use Pko\OrderNotifications\Mail\OrderDeliveredMail;
use Pko\OrderNotifications\Mail\OrderPaymentReceivedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * Déclenche les e-mails du parcours commande sur les transitions de statut.
 *
 * Les statuts sont ceux de `config/lunar/orders.php`. On s'appuie sur
 * `wasChanged('status')` plutôt que sur un événement métier dédié : c'est le
 * seul signal disponible, et le même que celui déjà utilisé par
 * `OrderShipmentObserver`.
 */
class OrderMailObserver
{
    /** Statuts signifiant que la commande est payée. */
    private const PAID_STATUSES = ['paid', 'payment-received'];

    /** Statuts pour lesquels une commande est considérée comme passée. */
    private const PLACED_STATUSES = ['awaiting-payment', 'payment-offline', 'paid', 'payment-received'];

    public function created(Order $order): void
    {
        // Une commande créée en brouillon (panier) n'est pas une commande passée.
        if ($order->placed_at === null) {
            return;
        }

        $this->send(new OrderConfirmedMail($order), $order);
    }

    public function updated(Order $order): void
    {
        // `placed_at` passe de null à une date quand le panier devient commande :
        // c'est ce moment-là, et pas la création de la ligne, qui vaut confirmation.
        if ($order->wasChanged('placed_at') && $order->placed_at !== null) {
            $this->send(new OrderConfirmedMail($order), $order);
        }

        if (! $order->wasChanged('status')) {
            return;
        }

        if (in_array($order->status, self::PAID_STATUSES, true)) {
            $this->send(new OrderPaymentReceivedMail($order), $order);
            $this->sendFirstOrderGift($order);
        }

        if ($order->status === 'delivered') {
            $this->send(new OrderDeliveredMail($order), $order);
        }
    }

    /**
     * E-mail 03 : uniquement si c'est la toute première commande passée du client.
     *
     * On compte les commandes précédentes du même client plutôt que de poser un
     * drapeau : un drapeau serait faux pour les clients importés d'un autre outil.
     */
    private function sendFirstOrderGift(Order $order): void
    {
        $customerId = $order->customer_id;

        if ($customerId === null) {
            return;
        }

        $previous = Order::query()
            ->where('customer_id', $customerId)
            ->where('id', '!=', $order->id)
            ->whereIn('status', self::PLACED_STATUSES)
            ->whereNotNull('placed_at')
            ->exists();

        if ($previous) {
            return;
        }

        $this->send(new FirstOrderGiftMail($order), $order);
    }

    /**
     * Passe par OnceMailer : un observer de statut se rejoue (reprise de webhook,
     * backfill, sauvegarde répétée) et enverrait sinon deux fois le même e-mail.
     *
     * OnceMailer avale ses propres erreurs d'envoi : l'observer tourne dans le
     * flux d'un paiement encaissé, une exception ferait échouer une transaction
     * métier déjà validée côté banque.
     */
    private function send(TemplatedMail $mail, Order $order): void
    {
        if (! $mail->shouldSend()) {
            return;
        }

        $recipient = OrderMailData::recipient($order);

        if ($recipient === null) {
            Log::info('Order mail skipped: no recipient', [
                'order_id' => $order->id,
                'mail_key' => $mail->key,
            ]);

            return;
        }

        OnceMailer::send($mail, $recipient, $order);
    }
}
