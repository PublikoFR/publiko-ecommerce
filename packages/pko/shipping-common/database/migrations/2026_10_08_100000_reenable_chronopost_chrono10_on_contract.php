<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chrono 10H est bien au contrat Chronopost (réponse du support du 2026-10-08) :
 * `productCode` 02, `service` 0 (semaine) / 6 (samedi). La migration du 2026-09-25 l'avait
 * désactivé à tort. Chrono 18 reste hors contrat.
 *
 * Idempotente : ne remplace pas un code produit saisi à la main dans le back-office.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pko_carrier_services') || ! Schema::hasColumn('pko_carrier_services', 'carrier_product_code')) {
            return;
        }

        $query = DB::table('pko_carrier_services')
            ->where('carrier_code', 'chronopost')
            ->where('service_code', 'chrono10');

        (clone $query)
            ->where(fn ($q) => $q->whereNull('carrier_product_code')->orWhere('carrier_product_code', ''))
            ->update(['carrier_product_code' => '02']);

        $query->update(['enabled' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pko_carrier_services')) {
            return;
        }

        DB::table('pko_carrier_services')
            ->where('carrier_code', 'chronopost')
            ->where('service_code', 'chrono10')
            ->update(['enabled' => false]);
    }
};
