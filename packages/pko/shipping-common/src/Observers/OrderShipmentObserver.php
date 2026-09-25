<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Observers;

use Lunar\Models\Order;
use Pko\ShippingCommon\Shipping\ShipmentLabelService;

/**
 * À l'encaissement, enregistre les envois transporteur « en attente ».
 *
 * Aucune étiquette n'est créée ici : c'est l'admin qui la crée depuis la fiche
 * commande (bouton « Créer l'étiquette »). Cf. ShipmentLabelService.
 */
class OrderShipmentObserver
{
    /** Lunar order statuses that mean the order has been paid. */
    private const PAID_STATUSES = ['paid', 'payment-received'];

    /*
     * Pourquoi pas de hook `created()` : au moment où la commande est créée, ses
     * adresses ne le sont pas encore (`CreateOrderAddresses` s'exécute après
     * `FillOrderFromCart`). Un observer `created` ne verrait donc jamais de
     * `shipping_option` — ce trou est couvert par `shipping:backfill-shipments`.
     */

    public function __construct(
        private readonly ShipmentLabelService $labels,
    ) {}

    public function updated(Order $order): void
    {
        // Lunar stores the paid state in `status` (the Stripe addon maps a succeeded
        // PaymentIntent to `payment-received`); there is no `payment_status` column.
        if (! $order->wasChanged('status')) {
            return;
        }

        if (! in_array($order->status, self::PAID_STATUSES, true)) {
            return;
        }

        $this->labels->recordPending($order);
    }
}
