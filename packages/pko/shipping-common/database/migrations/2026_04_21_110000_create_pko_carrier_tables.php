<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_carrier_services', function (Blueprint $table) {
            $table->id();
            $table->string('carrier_code', 64);
            $table->string('service_code', 64);
            $table->string('label', 255);
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['carrier_code', 'service_code']);
            $table->index('carrier_code');
        });

        Schema::create('pko_carrier_grids', function (Blueprint $table) {
            $table->id();
            $table->string('carrier_code', 64);
            $table->string('service_code', 64)->nullable()->comment('NULL = applies to all services of the carrier');
            $table->unsignedInteger('max_kg');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['carrier_code', 'service_code']);
            $table->index(['carrier_code', 'max_kg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_carrier_grids');
        Schema::dropIfExists('pko_carrier_services');
    }
};
