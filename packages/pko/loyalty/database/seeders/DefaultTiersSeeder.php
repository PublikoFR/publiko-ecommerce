<?php

declare(strict_types=1);

namespace Pko\Loyalty\Database\Seeders;

use Illuminate\Database\Seeder;
use Pko\Loyalty\Models\LoyaltyTier;

class DefaultTiersSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['name' => 'Bronze', 'points_required' => 500, 'gift_title' => "Bon d'achat 50€", 'position' => 1],
            ['name' => 'Argent', 'points_required' => 1000, 'gift_title' => 'TV Samsung 43"', 'position' => 2],
            ['name' => 'Or', 'points_required' => 2000, 'gift_title' => 'iPhone 15 Pro', 'position' => 3],
        ];

        foreach ($tiers as $tier) {
            LoyaltyTier::firstOrCreate(['name' => $tier['name']], $tier + ['active' => true]);
        }
    }
}
