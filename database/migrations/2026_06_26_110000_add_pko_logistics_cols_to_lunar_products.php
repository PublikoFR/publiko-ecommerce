<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_products', function (Blueprint $table) {
            $table->enum('pko_logistics_class', ['A', 'B', 'C'])->default('A')->after('pko_free_shipping');
            $table->boolean('pko_franco_eligible')->default(true)->index()->after('pko_logistics_class');
            $table->unsignedInteger('pko_transport_price_cents')->nullable()->after('pko_franco_eligible');
            $table->boolean('pko_quote_only')->default(false)->after('pko_transport_price_cents');
            $table->foreignId('pko_supplier_id')
                ->nullable()
                ->after('pko_quote_only')
                ->constrained('pko_suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lunar_products', function (Blueprint $table) {
            $table->dropForeign(['pko_supplier_id']);
            $table->dropIndex(['pko_franco_eligible']);
            $table->dropColumn([
                'pko_logistics_class',
                'pko_franco_eligible',
                'pko_transport_price_cents',
                'pko_quote_only',
                'pko_supplier_id',
            ]);
        });
    }
};
