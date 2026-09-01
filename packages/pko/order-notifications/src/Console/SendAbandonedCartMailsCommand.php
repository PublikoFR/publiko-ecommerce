<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Console;

use Illuminate\Console\Command;
use Lunar\Models\Cart;
use Pko\MailTemplates\Support\OnceMailer;
use Pko\OrderNotifications\Mail\AbandonedCartMail;

/**
 * E-mail 11 : relance des paniers restés sans commande.
 *
 * Un panier est « abandonné » s'il porte des lignes, n'a pas été converti en
 * commande, et n'a pas bougé depuis le délai configuré. La borne basse évite de
 * relancer des paniers oubliés depuis des semaines.
 */
class SendAbandonedCartMailsCommand extends Command
{
    protected $signature = 'pko:mails:abandoned-carts {--dry-run : Affiche les destinataires sans envoyer}';

    protected $description = 'Relance les clients dont le panier n\'a pas été finalisé.';

    public function handle(): int
    {
        $hours = (int) config('order-notifications.abandoned_cart_hours', 24);
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        $carts = Cart::query()
            ->whereNull('completed_at')
            ->whereNull('order_id')
            ->whereNotNull('user_id')
            ->where('updated_at', '<=', now()->subHours($hours))
            ->where('updated_at', '>=', now()->subDays(7))
            ->whereHas('lines')
            ->with(['user', 'customer'])
            ->get();

        foreach ($carts as $cart) {
            $email = $cart->user?->email;

            if (empty($email)) {
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] panier #{$cart->id} → {$email}");

                continue;
            }

            if (OnceMailer::send(new AbandonedCartMail($cart), $email, $cart)) {
                $sent++;
            }
        }

        $this->info("Relances panier : {$sent} envoyee(s) sur {$carts->count()} panier(s) eligible(s).");

        return self::SUCCESS;
    }
}
