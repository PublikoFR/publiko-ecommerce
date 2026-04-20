<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_product_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('lunar_products')
                ->cascadeOnDelete();
            $table->foreignId('media_id')
                ->constrained('media')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('pko_document_categories')
                ->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'media_id']);
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_product_documents');
    }
};
