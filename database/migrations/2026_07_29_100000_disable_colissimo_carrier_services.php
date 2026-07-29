<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L1 — Refonte frais de port 2026 : Colissimo mis en veille.
 *
 * On livre uniquement en France via Chronopost. Les services Colissimo
 * (DOM, DOS) sont désactivés en DB pour qu'aucune option colissimo.*
 * ne sorte du manifest au checkout. Le package reste en place et peut
 * être réactivé en repassant enabled=1 + en décommentant shouldRegisterNavigation()
 * dans ColissimoConfig.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pko_carrier_services')
            ->where('carrier_code', 'colissimo')
            ->update(['enabled' => false]);
    }

    public function down(): void
    {
        DB::table('pko_carrier_services')
            ->where('carrier_code', 'colissimo')
            ->update(['enabled' => true]);
    }
};
