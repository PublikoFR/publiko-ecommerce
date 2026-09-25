<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne les services Chronopost sur la doc officielle Web Services VL3.25.10.10 et
 * sur le contrat réel.
 *
 * - `productCode` est un code à DEUX caractères : `1` / `2` (repris du module
 *   PrestaShop par la migration 2026_07_31_100000) sont refusés par le WS (erreur 33)
 *   et par la regex du SDK, qui l'ignorait en silence → aucune étiquette Chrono 13
 *   n'était créable.
 * - Chrono 10 et Chrono 18 ne sont pas au contrat : désactivés (pas supprimés, pour
 *   garder les grilles et l'historique des envois).
 *
 * Idempotente : ne réécrit que les valeurs connues comme fausses, ne touche pas un code
 * saisi à la main dans le back-office.
 */
return new class extends Migration
{
    private const PRODUCT_CODE_FIXES = [
        'chrono13' => ['1', '01'],
        'chrono10' => ['2', '02'],
    ];

    private const OFF_CONTRACT = ['chrono10', 'chrono18'];

    public function up(): void
    {
        if (! Schema::hasTable('pko_carrier_services') || ! Schema::hasColumn('pko_carrier_services', 'carrier_product_code')) {
            return;
        }

        foreach (self::PRODUCT_CODE_FIXES as $serviceCode => [$wrong, $right]) {
            DB::table('pko_carrier_services')
                ->where('carrier_code', 'chronopost')
                ->where('service_code', $serviceCode)
                ->where(fn ($q) => $q->where('carrier_product_code', $wrong)->orWhereNull('carrier_product_code'))
                ->update(['carrier_product_code' => $right]);
        }

        DB::table('pko_carrier_services')
            ->where('carrier_code', 'chronopost')
            ->whereIn('service_code', self::OFF_CONTRACT)
            ->update(['enabled' => false]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pko_carrier_services') || ! Schema::hasColumn('pko_carrier_services', 'carrier_product_code')) {
            return;
        }

        foreach (self::PRODUCT_CODE_FIXES as $serviceCode => [$wrong, $right]) {
            DB::table('pko_carrier_services')
                ->where('carrier_code', 'chronopost')
                ->where('service_code', $serviceCode)
                ->where('carrier_product_code', $right)
                ->update(['carrier_product_code' => $wrong]);
        }

        // La désactivation de chrono10 / chrono18 n'est pas annulée : réactiver un
        // service hors contrat doit rester un geste explicite du back-office.
    }
};
