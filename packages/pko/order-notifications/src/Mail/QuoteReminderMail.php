<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 10 « Relance devis ».
 *
 * Distinct de QuotePaymentLinkMail (shipping-common), qui transmet le lien de
 * paiement signé : celui-ci ne fait que relancer un devis resté sans réponse.
 */
class QuoteReminderMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct('quote.reminder', [
            'first_name' => OrderMailData::firstName($order),
            'quote_reference' => (string) $order->reference,
            'quote_url' => url('/compte/devis/'.$order->reference),
        ]);
    }
}
