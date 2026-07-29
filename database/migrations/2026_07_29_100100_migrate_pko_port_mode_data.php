<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion des anciennes colonnes (pko_free_shipping, pko_quote_only,
 * pko_logistics_class) vers pko_port_mode, puis suppression des colonnes source.
 *
 * Idempotente : s'arrête immédiatement si pko_logistics_class n'existe plus
 * (migration déjà exécutée ou colonnes source jamais posées).
 *
 * Priorité de conversion :
 *   1. pko_quote_only=true OU pko_logistics_class='C' → 'quote'
 *   2. pko_free_shipping=true (et non-quote)          → 'free'
 *   3. pko_supplier_id IS NOT NULL (et non-quote/free) → 'inherit'
 *   4. sinon                                           → 'standard'
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lunar_products', 'pko_logistics_class')) {
            return;
        }

        DB::statement("
            UPDATE lunar_products
            SET pko_port_mode = CASE
                WHEN pko_quote_only = 1 OR pko_logistics_class = 'C' THEN 'quote'
                WHEN pko_free_shipping = 1                            THEN 'free'
                WHEN pko_supplier_id IS NOT NULL                      THEN 'inherit'
                ELSE 'standard'
            END
        ");

        Schema::table('lunar_products', function ($table): void {
            $table->dropColumn(['pko_logistics_class', 'pko_free_shipping', 'pko_quote_only']);
        });
    }

    public function down(): void
    {
        // Recréation des colonnes source sans restaurer les données (perte acceptable en rollback dev).
        if (! Schema::hasColumn('lunar_products', 'pko_logistics_class')) {
            Schema::table('lunar_products', function ($table): void {
                $table->enum('pko_logistics_class', ['A', 'B', 'C'])->default('A')->nullable()->after('pko_supplier_id');
                $table->boolean('pko_free_shipping')->default(false)->after('pko_logistics_class');
                $table->boolean('pko_quote_only')->default(false)->after('pko_free_shipping');
            });
        }
    }
};
