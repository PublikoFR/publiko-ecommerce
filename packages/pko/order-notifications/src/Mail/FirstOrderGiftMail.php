<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 03 « Première commande » — envoyé à la première commande payée d'un client.
 */
class FirstOrderGiftMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct('order.first_order_gift', OrderMailData::base($order));
    }
}
