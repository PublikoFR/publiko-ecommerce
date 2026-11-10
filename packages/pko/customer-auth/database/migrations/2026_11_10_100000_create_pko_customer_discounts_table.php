<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remises client permanentes (en %) par cible catalogue : collection (famille /
 * sous-famille / sous-sous-famille, héritage descendant), famille fabricant ou
 * marque. Cible polymorphe « légère » (target_type + target_id) : pas de FK
 * car la cible vit dans des tables différentes (dont une d'un autre package).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pko_customer_discounts')) {
            return;
        }

        Schema::create('pko_customer_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('lunar_customers')->cascadeOnDelete();
            // collection | brand_family | brand (cf. CustomerDiscount::TARGET_*).
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            // Pourcentage de remise, 2 décimales (ex. 12.50 = -12,5 %).
            $table->decimal('percent', 5, 2);
            $table->timestamps();

            $table->unique(['customer_id', 'target_type', 'target_id'], 'pko_customer_discounts_unique');
            $table->index(['target_type', 'target_id'], 'pko_customer_discounts_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_customer_discounts');
    }
};
