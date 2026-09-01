<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 05 « Paiement confirmé » — envoyé au passage en statut payé.
 */
class OrderPaymentReceivedMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct('order.payment_received', OrderMailData::base($order));
    }
}
