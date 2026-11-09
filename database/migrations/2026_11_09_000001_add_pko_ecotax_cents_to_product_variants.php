<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Éco-participation par variante, en CENTS HT par unité. Modèle « dont » : le
 * montant est INCLUS dans le prix de vente (cf. docs/ecotax.md). NULL = jamais
 * renseignée (le rattrapage `ai-importer:backfill-ecotax` ne traite que ces
 * lignes), 0 = renseignée et nulle. Colonne custom `pko_` ajoutée à la table
 * Lunar via Schema::table() (jamais modifier la migration Lunar publiée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lunar_product_variants', 'pko_ecotax_cents')) {
            return;
        }

        Schema::table('lunar_product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('pko_ecotax_cents')->nullable()->after('pko_cost_price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lunar_product_variants', 'pko_ecotax_cents')) {
            return;
        }

        Schema::table('lunar_product_variants', function (Blueprint $table): void {
            $table->dropColumn('pko_ecotax_cents');
        });
    }
};
