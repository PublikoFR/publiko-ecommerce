<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compatibilités documentées d'une pièce détachée avec des équipements.
 *
 * Distinct de la marque Lunar du produit (marque DE LA PIÈCE) : chaque ligne
 * décrit un équipement avec lequel la pièce est compatible. L'équipement peut
 * être une marque du catalogue (`equipment_brand_id`) ou un libellé libre
 * (`equipment_brand_label`) quand la marque n'est pas référencée, et pointer
 * éventuellement vers un produit du catalogue (`compatible_product_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pko_product_compatibilities')) {
            return;
        }

        Schema::create('pko_product_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
            $table->foreignId('equipment_brand_id')->nullable()->constrained('lunar_brands')->nullOnDelete();
            $table->string('equipment_brand_label')->nullable();
            $table->string('equipment_model')->nullable();
            $table->string('equipment_reference')->nullable();
            $table->foreignId('compatible_product_id')->nullable()->constrained('lunar_products')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            // Recherche storefront par référence / modèle d'équipement.
            $table->index('equipment_reference');
            $table->index('equipment_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_product_compatibilities');
    }
};
