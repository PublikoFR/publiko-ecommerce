<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Console;

use Illuminate\Console\Command;
use Lunar\Models\Order;
use Pko\MailTemplates\Support\OnceMailer;
use Pko\OrderNotifications\Mail\OrderReviewRequestMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 13 : demande d'avis, quelques jours après la livraison.
 *
 * Différée volontairement : demander un avis le jour de la livraison, avant que
 * le client ait ouvert le colis, ne produit pas de retour utile.
 */
class SendReviewRequestsCommand extends Command
{
    protected $signature = 'pko:mails:review-requests {--dry-run : Affiche les destinataires sans envoyer}';

    protected $description = 'Envoie les demandes d\'avis pour les commandes livrées.';

    public function handle(): int
    {
        if ((string) config('order-notifications.review_url', '') === '') {
            $this->warn('ORDER_REVIEW_URL non configurée : aucune demande d\'avis envoyée.');

            return self::SUCCESS;
        }

        $days = (int) config('order-notifications.review_delay_days', 7);
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        $orders = Order::query()
            ->where('status', 'delivered')
            ->where('updated_at', '<=', now()->subDays($days))
            ->where('updated_at', '>=', now()->subDays($days + 30))
            ->get();

        foreach ($orders as $order) {
            $recipient = OrderMailData::recipient($order);

            if ($recipient === null) {
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] commande {$order->reference} → {$recipient}");

                continue;
            }

            if (OnceMailer::send(new OrderReviewRequestMail($order), $recipient, $order)) {
                $sent++;
            }
        }

        $this->info("Demandes d'avis : {$sent} envoyee(s).");

        return self::SUCCESS;
    }
}
