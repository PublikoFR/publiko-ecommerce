<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('bl_neutre')->default(false);
            $table->unsignedInteger('lead_time_min_days')->nullable();
            $table->unsignedInteger('lead_time_max_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_suppliers');
    }
};
