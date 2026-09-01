<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 13 « Demande d'avis ».
 *
 * La destination du bouton n'est pas décidée par le code : plateforme d'avis
 * externe ou formulaire interne, c'est un réglage (`ORDER_REVIEW_URL`). Tant
 * qu'il est vide, `shouldSend()` est faux et aucun mail ne part — plutôt qu'un
 * bouton mort envoyé à tous les clients.
 */
class OrderReviewRequestMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct('order.review_request', OrderMailData::base($order) + [
            'review_url' => (string) config('order-notifications.review_url', ''),
        ]);
    }

    public function shouldSend(): bool
    {
        return parent::shouldSend() && $this->values['review_url'] !== '';
    }
}
