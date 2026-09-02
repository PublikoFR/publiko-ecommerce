<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Console;

use Illuminate\Console\Command;
use Lunar\Models\Order;
use Pko\MailTemplates\Support\OnceMailer;
use Pko\OrderNotifications\Mail\QuoteReminderMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 10 : relance des devis restés sans réponse.
 */
class SendQuoteRemindersCommand extends Command
{
    protected $signature = 'pko:mails:quote-reminders {--dry-run : Affiche les destinataires sans envoyer}';

    protected $description = 'Relance les devis en attente depuis le délai configuré.';

    public function handle(): int
    {
        $days = (int) config('order-notifications.quote_reminder_days', 5);
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        $orders = Order::query()
            ->where('status', 'awaiting-quote')
            ->where('updated_at', '<=', now()->subDays($days))
            ->where('updated_at', '>=', now()->subDays(60))
            ->get();

        foreach ($orders as $order) {
            $recipient = OrderMailData::recipient($order);

            if ($recipient === null) {
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] devis {$order->reference} → {$recipient}");

                continue;
            }

            if (OnceMailer::send(new QuoteReminderMail($order), $recipient, $order)) {
                $sent++;
            }
        }

        $this->info("Relances devis : {$sent} envoyee(s) sur {$orders->count()} devis eligible(s).");

        return self::SUCCESS;
    }
}
