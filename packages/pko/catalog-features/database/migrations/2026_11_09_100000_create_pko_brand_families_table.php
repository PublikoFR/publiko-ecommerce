<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Familles fabricant (familles de remise FAB-DIS : FAM1/FAM1L, GAMME/FONCTION…).
 *
 * Donnée PRIVÉE : jamais exposée au front ni à l'API. Un code n'a de sens que
 * dans sa marque (« ACM » chez deux fabricants = deux familles distinctes),
 * d'où l'unicité (brand_id, code). Le code est stocké EXACT : collation binaire
 * en MySQL, sinon `utf8mb4_unicode_ci` confondrait « acm » et « ACM ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pko_brand_families')) {
            Schema::create('pko_brand_families', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('lunar_brands')->cascadeOnDelete();
                $code = $table->string('code', 100);
                if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $code->collation('utf8mb4_bin');
                }
                $table->string('label')->nullable();
                $table->timestamps();

                $table->unique(['brand_id', 'code']);
            });
        }

        if (! Schema::hasColumn('lunar_products', 'pko_brand_family_id')) {
            Schema::table('lunar_products', function (Blueprint $table) {
                $table->foreignId('pko_brand_family_id')
                    ->nullable()
                    ->constrained('pko_brand_families')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lunar_products', 'pko_brand_family_id')) {
            Schema::table('lunar_products', function (Blueprint $table) {
                $table->dropForeign(['pko_brand_family_id']);
                $table->dropColumn('pko_brand_family_id');
            });
        }

        Schema::dropIfExists('pko_brand_families');
    }
};
