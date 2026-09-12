<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Console;

use Illuminate\Console\Command;
use Lunar\Models\Cart;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\OnceMailer;
use Pko\OrderNotifications\Mail\AbandonedCartMail;

/**
 * E-mail 11 : relance des paniers restés sans commande.
 *
 * Un panier est « abandonné » s'il porte des lignes, n'a pas été converti en
 * commande, et n'a pas bougé depuis le délai configuré. La commande peut être
 * rejouée sans risque de double envoi : OnceMailer pose un verrou idempotent
 * (contrainte unique en base) avant chaque envoi.
 *
 * Priorité du délai :
 *   1. Colonne `settings.delay_days` du modèle `cart.abandoned` (back-office)
 *   2. `config('order-notifications.abandoned_cart_hours')` / 24 (env var ou config)
 */
class SendAbandonedCartMailsCommand extends Command
{
    protected $signature = 'pko:mails:abandoned-carts {--dry-run : Affiche les destinataires sans envoyer}';

    protected $description = 'Relance les clients dont le panier n\'a pas été finalisé.';

    public function handle(): int
    {
        $days = $this->resolveDelayDays();
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        // Fenêtre glissante : on cherche les paniers abandonnés depuis [$days ; $days+14]
        // jours. Le +14 évite de manquer un panier si la commande ne tourne pas
        // exactement à l'heure. OnceMailer empêche le double envoi.
        $carts = Cart::query()
            ->whereNull('completed_at')
            ->whereNull('order_id')
            ->whereNotNull('user_id')
            ->where('updated_at', '<=', now()->subDays($days))
            ->where('updated_at', '>=', now()->subDays($days + 14))
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

    /**
     * Délai en jours avant la relance.
     *
     * Lit d'abord `settings.delay_days` depuis la base (configurable en
     * back-office), puis tombe sur `config('order-notifications.abandoned_cart_hours')`.
     */
    private function resolveDelayDays(): int
    {
        $template = MailTemplate::query()
            ->where('key', 'cart.abandoned')
            ->first(['settings']);

        $fromDb = (int) ($template?->settings['delay_days'] ?? 0);

        if ($fromDb > 0) {
            return $fromDb;
        }

        // Fallback : la config est en heures (compatibilité avec l'env var existante).
        $hours = (int) config('order-notifications.abandoned_cart_hours', 120);

        return (int) max(1, round($hours / 24));
    }
}
