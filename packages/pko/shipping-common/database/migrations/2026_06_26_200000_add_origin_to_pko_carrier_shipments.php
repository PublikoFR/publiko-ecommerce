<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_carrier_shipments', function (Blueprint $table): void {
            $table->string('origin', 32)->default('weklo')->after('carrier');

            // Replace the simple carrier index with a unique triplet
            // so N shipments of the same order/carrier can coexist when they
            // serve different stock origins.
            $table->dropIndex(['carrier']);
            $table->unique(['order_id', 'carrier', 'origin'], 'pko_carrier_shipments_order_carrier_origin_unique');
            $table->index(['status', 'carrier', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::table('pko_carrier_shipments', function (Blueprint $table): void {
            $table->dropUnique('pko_carrier_shipments_order_carrier_origin_unique');
            $table->dropIndex(['status', 'carrier', 'origin']);
            $table->dropColumn('origin');
            $table->index('carrier');
        });
    }
};
