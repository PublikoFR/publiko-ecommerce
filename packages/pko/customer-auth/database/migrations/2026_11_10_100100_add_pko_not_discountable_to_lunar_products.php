<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case produit « non remisable » : exclut le produit de toute remise client en %
 * (le prix négocié reste applicable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lunar_products', 'pko_not_discountable')) {
            return;
        }

        Schema::table('lunar_products', function (Blueprint $table) {
            $table->boolean('pko_not_discountable')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lunar_products', 'pko_not_discountable')) {
            return;
        }

        Schema::table('lunar_products', function (Blueprint $table) {
            $table->dropColumn('pko_not_discountable');
        });
    }
};
