<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Customer;
use Pko\MailTemplates\Support\OnceMailer;
use Pko\OrderNotifications\Mail\LoyaltyAnniversaryMail;

/**
 * E-mail 18 : anniversaire de la première commande.
 *
 * Exécution quotidienne : on cherche les clients dont la première commande
 * passée tombe, au jour et au mois près, sur la date du jour.
 */
class SendLoyaltyAnniversaryMailsCommand extends Command
{
    protected $signature = 'pko:mails:loyalty-anniversary {--dry-run : Affiche les destinataires sans envoyer}';

    protected $description = 'Envoie l\'e-mail d\'anniversaire de fidélité aux clients concernés.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = now();
        $sent = 0;

        // Première commande passée par client, tous statuts « commande » confondus.
        $firstOrders = DB::table('lunar_orders')
            ->select('customer_id', DB::raw('MIN(placed_at) as first_placed_at'))
            ->whereNotNull('customer_id')
            ->whereNotNull('placed_at')
            ->groupBy('customer_id')
            ->get();

        foreach ($firstOrders as $row) {
            $firstDate = Carbon::parse($row->first_placed_at);

            if ($firstDate->format('m-d') !== $today->format('m-d')) {
                continue;
            }

            $years = $firstDate->diffInYears($today);

            // Le jour même de la première commande n'est pas un anniversaire.
            if ($years < 1) {
                continue;
            }

            $customer = Customer::find($row->customer_id);
            $email = $customer?->users()->first()?->email;

            if ($customer === null || empty($email)) {
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] client #{$customer->id} ({$years} an(s)) → {$email}");

                continue;
            }

            // Portée = l'année civile : sans elle, la garde anti-doublon ne
            // laisserait passer que le tout premier anniversaire du client.
            if (OnceMailer::send(
                new LoyaltyAnniversaryMail($customer, (int) $years),
                $email,
                $customer,
                (string) $today->year,
            )) {
                $sent++;
            }
        }

        $this->info("Anniversaires fidelite : {$sent} envoye(s).");

        return self::SUCCESS;
    }
}
