<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds pko_carrier_services and pko_carrier_grids with the initial Chronopost
 * and Colissimo data previously hard-coded in each carrier's config file.
 *
 * Idempotent: only inserts if the tables are empty for the given carrier_code,
 * so rolling back a carrier package or re-running on an existing install will
 * not duplicate data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->seedCarrier('chronopost', [
            'services' => [
                ['service_code' => '13', 'label' => 'Chrono 13 (avant 13h)', 'enabled' => true, 'sort' => 10],
                ['service_code' => '16', 'label' => 'Chrono 18 (avant fin de journée)', 'enabled' => false, 'sort' => 20],
                ['service_code' => '02', 'label' => 'Chrono Classic (J+1)', 'enabled' => true, 'sort' => 30],
            ],
            'grid' => [
                ['max_kg' => 2, 'price_cents' => 1290, 'sort' => 10],
                ['max_kg' => 5, 'price_cents' => 1590, 'sort' => 20],
                ['max_kg' => 10, 'price_cents' => 1990, 'sort' => 30],
                ['max_kg' => 20, 'price_cents' => 2890, 'sort' => 40],
                ['max_kg' => 30, 'price_cents' => 3990, 'sort' => 50],
            ],
        ]);

        $this->seedCarrier('colissimo', [
            'services' => [
                ['service_code' => 'DOM', 'label' => 'Domicile sans signature', 'enabled' => true, 'sort' => 10],
                ['service_code' => 'DOS', 'label' => 'Domicile avec signature', 'enabled' => true, 'sort' => 20],
            ],
            'grid' => [
                ['max_kg' => 2, 'price_cents' => 690, 'sort' => 10],
                ['max_kg' => 5, 'price_cents' => 990, 'sort' => 20],
                ['max_kg' => 10, 'price_cents' => 1490, 'sort' => 30],
                ['max_kg' => 30, 'price_cents' => 2490, 'sort' => 40],
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('pko_carrier_grids')->whereIn('carrier_code', ['chronopost', 'colissimo'])->delete();
        DB::table('pko_carrier_services')->whereIn('carrier_code', ['chronopost', 'colissimo'])->delete();
    }

    private function seedCarrier(string $code, array $data): void
    {
        $now = now();

        if (DB::table('pko_carrier_services')->where('carrier_code', $code)->doesntExist()) {
            foreach ($data['services'] as $service) {
                DB::table('pko_carrier_services')->insert(array_merge($service, [
                    'carrier_code' => $code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        if (DB::table('pko_carrier_grids')->where('carrier_code', $code)->doesntExist()) {
            foreach ($data['grid'] as $bracket) {
                DB::table('pko_carrier_grids')->insert(array_merge($bracket, [
                    'carrier_code' => $code,
                    'service_code' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
};
