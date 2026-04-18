<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('mediable_type');
            $table->unsignedBigInteger('mediable_id');
            $table->string('mediagroup')->default('default');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'mediagroup', 'position'], 'pko_mediables_morph_group_idx');
            $table->unique(['media_id', 'mediable_type', 'mediable_id', 'mediagroup'], 'pko_mediables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_mediables');
    }
};
