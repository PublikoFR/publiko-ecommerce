<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 12 « Commande livrée » — envoyé au passage en statut livré.
 */
class OrderDeliveredMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct('order.delivered', OrderMailData::base($order));
    }
}
