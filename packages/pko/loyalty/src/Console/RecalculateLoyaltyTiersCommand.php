<?php

declare(strict_types=1);

namespace Pko\Loyalty\Console;

use Illuminate\Console\Command;
use Pko\Loyalty\Models\CustomerPoints;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Services\LoyaltyManager;

/**
 * Rejoue le déblocage des paliers de fidélité pour tous les clients — rattrape
 * les paliers ajoutés après coup sous le solde déjà acquis d'un client, ou
 * sautés lors d'une commande qui a fait franchir plusieurs paliers d'un coup.
 * Idempotente : ne crée que les GiftHistory manquantes, n'en supprime aucune.
 */
class RecalculateLoyaltyTiersCommand extends Command
{
    protected $signature = 'loyalty:recalculate {--dry-run : Compte les cadeaux qui seraient débloqués sans rien modifier ni notifier}';

    protected $description = 'Rattrape les paliers de fidélité éligibles mais jamais débloqués pour tous les clients';

    public function handle(LoyaltyManager $manager): int
    {
        $customersPoints = CustomerPoints::query()->get();

        if ($customersPoints->isEmpty()) {
            $this->info('Aucun client avec des points de fidélité.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $count = 0;

            foreach ($customersPoints as $cp) {
                if ((int) $cp->points_year !== $manager->currentYear()) {
                    continue;
                }

                $eligibleTierIds = LoyaltyTier::query()
                    ->where('active', true)
                    ->where('points_required', '<=', $cp->total_points)
                    ->pluck('id');

                $alreadyUnlockedTierIds = GiftHistory::query()
                    ->where('customer_id', $cp->customer_id)
                    ->where('year', $manager->currentYear())
                    ->whereIn('tier_id', $eligibleTierIds)
                    ->pluck('tier_id');

                $count += $eligibleTierIds->diff($alreadyUnlockedTierIds)->count();
            }

            $this->warn($count.' cadeau(x) seraient débloqués sur '.$customersPoints->count().' client(s) (dry-run, rien écrit).');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($customersPoints as $cp) {
            $total += $manager->recalculateForCustomer($cp);
        }

        $this->info($total.' cadeau(x) débloqué(s) sur '.$customersPoints->count().' client(s).');

        return self::SUCCESS;
    }
}
