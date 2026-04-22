<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_carrier_shipments', function (Blueprint $table) {
            $table->string('delivery_status', 32)->nullable()->after('status');
            $table->timestamp('delivery_status_updated_at')->nullable()->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('delivery_status_updated_at');
            $table->json('tracking_events')->nullable()->after('delivered_at');
            $table->timestamp('notified_customer_at')->nullable()->after('tracking_events');

            $table->index(['carrier', 'delivery_status']);
        });
    }

    public function down(): void
    {
        Schema::table('pko_carrier_shipments', function (Blueprint $table) {
            $table->dropIndex(['carrier', 'delivery_status']);
            $table->dropColumn([
                'delivery_status',
                'delivery_status_updated_at',
                'delivered_at',
                'tracking_events',
                'notified_customer_at',
            ]);
        });
    }
};
