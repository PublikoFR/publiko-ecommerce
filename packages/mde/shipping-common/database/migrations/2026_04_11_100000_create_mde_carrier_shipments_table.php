<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        Schema::create('mde_carrier_shipments', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('order_id')
                ->constrained($prefix.'orders')
                ->cascadeOnDelete();
            $table->string('carrier', 32);
            $table->string('service_code', 32)->nullable();
            $table->string('tracking_number', 64)->nullable();
            $table->string('label_path', 255)->nullable();
            $table->string('status', 16)->default('pending');
            $table->json('payload_sent')->nullable();
            $table->json('response_received')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('carrier');
            $table->index('tracking_number');
            $table->index(['status', 'carrier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mde_carrier_shipments');
    }
};
