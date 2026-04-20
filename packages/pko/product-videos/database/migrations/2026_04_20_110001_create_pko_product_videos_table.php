<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_product_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('lunar_products')
                ->cascadeOnDelete();
            $table->text('url');
            $table->string('provider', 32);
            $table->string('provider_video_id')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            // NB: url est TEXT, pas d'index UNIQUE DB. L'unicité est garantie
            // côté app via ProductVideoManager::addIfNotExists().
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_product_videos');
    }
};
