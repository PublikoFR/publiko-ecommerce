<?php

declare(strict_types=1);

namespace Pko\Loyalty\Console;

use Illuminate\Console\Command;
use Pko\Loyalty\Services\LoyaltyManager;

/**
 * Remet à zéro les soldes de points d'une année passée (1er janvier).
 * Planifiée chaque jour : idempotente, elle rattrape un passage manqué du
 * scheduler. Les écritures de points font aussi la remise à zéro à la volée.
 */
class ResetAnnualLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:reset-annual';

    protected $description = 'Remet à zéro les points de fidélité des années passées';

    public function handle(LoyaltyManager $manager): int
    {
        $count = $manager->resetExpiredBalances();

        $this->info($count.' solde(s) remis à zéro pour '.$manager->currentYear().'.');

        return self::SUCCESS;
    }
}
