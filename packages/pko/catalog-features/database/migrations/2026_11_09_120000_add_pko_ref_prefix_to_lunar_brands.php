<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préfixe de référence boutique d'une marque (fabricant) : la référence
 * boutique d'un produit = préfixe + référence fabricant (variant `mpn`), cf.
 * docs/product-reference.md. Nullable (marque pas encore arbitrée), unique
 * (deux fabricants ne partagent jamais un préfixe). Aucune valeur n'est posée
 * ici : `pko:brands:ref-prefixes` propose et liste les collisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lunar_brands', 'pko_ref_prefix')) {
            return;
        }

        Schema::table('lunar_brands', function (Blueprint $table) {
            $table->string('pko_ref_prefix', 5)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lunar_brands', 'pko_ref_prefix')) {
            return;
        }

        Schema::table('lunar_brands', function (Blueprint $table) {
            $table->dropUnique(['pko_ref_prefix']);
            $table->dropColumn('pko_ref_prefix');
        });
    }
};
