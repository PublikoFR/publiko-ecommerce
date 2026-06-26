<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces Chronopost services and grid with the 3-service model (Relais / Chrono 13 / Chrono 10)
 * and per-service pricing brackets. Prices in cents HT.
 *
 * Chrono Relais has no 30 kg bracket → masked automatically above 20 kg.
 * Colissimo data is untouched (null-based shared grid remains valid).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('pko_carrier_grids')->where('carrier_code', 'chronopost')->delete();
            DB::table('pko_carrier_services')->where('carrier_code', 'chronopost')->delete();

            $now = now();

            DB::table('pko_carrier_services')->insert([
                [
                    'carrier_code' => 'chronopost',
                    'service_code' => 'chrono_relais',
                    'label' => 'Livraison économique — Chrono Relais',
                    'description' => 'Livraison en point relais Pickup — colis ≤ 20 kg',
                    'enabled' => true,
                    'sort' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'carrier_code' => 'chronopost',
                    'service_code' => 'chrono13',
                    'label' => 'Livraison standard — Chrono 13',
                    'description' => 'Livraison à domicile J+1 avant 13h',
                    'enabled' => true,
                    'sort' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'carrier_code' => 'chronopost',
                    'service_code' => 'chrono10',
                    'label' => 'Livraison express — Chrono 10',
                    'description' => 'Livraison à domicile J+1 avant 10h',
                    'enabled' => true,
                    'sort' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $grids = [
                // chrono_relais — pas de bracket 30 kg → masqué au-delà de 20 kg
                ['chrono_relais', 2, 1490, 10],
                ['chrono_relais', 5, 1790, 20],
                ['chrono_relais', 10, 2290, 30],
                ['chrono_relais', 20, 3290, 40],
                // chrono13 (défaut)
                ['chrono13', 2, 1890, 10],
                ['chrono13', 5, 2290, 20],
                ['chrono13', 10, 2790, 30],
                ['chrono13', 20, 3990, 40],
                ['chrono13', 30, 5490, 50],
                // chrono10
                ['chrono10', 2, 2490, 10],
                ['chrono10', 5, 2890, 20],
                ['chrono10', 10, 3490, 30],
                ['chrono10', 20, 4990, 40],
                ['chrono10', 30, 6990, 50],
            ];

            foreach ($grids as [$serviceCode, $maxKg, $priceCents, $sort]) {
                DB::table('pko_carrier_grids')->insert([
                    'carrier_code' => 'chronopost',
                    'service_code' => $serviceCode,
                    'max_kg' => $maxKg,
                    'price_cents' => $priceCents,
                    'sort' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('pko_carrier_grids')->where('carrier_code', 'chronopost')->delete();
        DB::table('pko_carrier_services')->where('carrier_code', 'chronopost')->delete();
    }
};
