<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_ai_importer_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128)->unique();
            $table->string('supplier_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('config_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_ai_importer_configs');
    }
};
