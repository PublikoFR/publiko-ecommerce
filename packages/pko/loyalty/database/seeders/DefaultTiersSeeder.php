<?php

declare(strict_types=1);

namespace Pko\Loyalty\Database\Seeders;

use Illuminate\Database\Seeder;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Models\LoyaltyTier;

/**
 * Paliers de démonstration, un par type de cadeau. Montants HT en cents.
 */
class DefaultTiersSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['name' => 'Bronze', 'points_required' => 500, 'gift_type' => GiftType::Voucher, 'voucher_amount' => 50_00, 'position' => 1],
            ['name' => 'Argent', 'points_required' => 1000, 'gift_type' => GiftType::Franco, 'franco_threshold' => 300_00, 'position' => 2],
            ['name' => 'Or', 'points_required' => 2000, 'gift_type' => GiftType::Bfa, 'bfa_fixed_amount' => 100_00, 'bfa_percent' => '2.00', 'position' => 3],
        ];

        foreach ($tiers as $tier) {
            LoyaltyTier::firstOrCreate(['name' => $tier['name']], $tier + ['active' => true]);
        }
    }
}
