<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 04 « Confirmation de commande » — envoyé à la création de la commande.
 */
class OrderConfirmedMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct('order.confirmed', OrderMailData::base($order));
    }
}
