<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prix d'achat (coût) par variant, en CENTS entiers — nécessaire au calcul de
 * marge. Colonne custom `pko_` ajoutée à la table Lunar via Schema::table()
 * (jamais modifier la migration Lunar publiée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lunar_product_variants', 'pko_cost_price')) {
            return;
        }

        Schema::table('lunar_product_variants', function (Blueprint $table): void {
            $table->unsignedBigInteger('pko_cost_price')->nullable()->after('mpn');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lunar_product_variants', 'pko_cost_price')) {
            return;
        }

        Schema::table('lunar_product_variants', function (Blueprint $table): void {
            $table->dropColumn('pko_cost_price');
        });
    }
};
