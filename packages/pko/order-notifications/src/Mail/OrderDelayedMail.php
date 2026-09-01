<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 09 « Retard de commande ».
 *
 * Aucun statut Lunar ne représente un retard : l'envoi est déclenché à la main
 * depuis le back-office, avec la nouvelle date estimée saisie par l'opérateur.
 */
class OrderDelayedMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(
        public readonly Order $order,
        public readonly Carbon $newDate,
    ) {
        parent::__construct('order.delayed', OrderMailData::base($order) + [
            'new_date' => $newDate->locale('fr')->isoFormat('D MMMM YYYY'),
        ]);
    }
}
