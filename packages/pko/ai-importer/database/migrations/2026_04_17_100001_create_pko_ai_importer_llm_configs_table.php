<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_ai_importer_llm_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('provider', 32);
            $table->text('api_key'); // encrypted via Eloquent cast
            $table->string('model', 64);
            $table->json('options')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('provider');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_ai_importer_llm_configs');
    }
};
