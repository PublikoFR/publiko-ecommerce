<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page de contenu 1-to-1 par marque (Lunar Brand), gérée via le page-builder
 * universel. Permet d'attribuer un layout custom et un contenu JSON à chaque
 * marque sans toucher à la table `lunar_brands` (vendor intouchable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_brand_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')
                ->unique()
                ->constrained('lunar_brands')
                ->cascadeOnDelete();
            $table->string('layout')->nullable();
            $table->json('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_brand_pages');
    }
};
