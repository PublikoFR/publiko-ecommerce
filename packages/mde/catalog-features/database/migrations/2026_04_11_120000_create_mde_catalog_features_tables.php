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

        Schema::create('mde_feature_families', function (Blueprint $table): void {
            $table->id();
            $table->string('handle', 64)->unique();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('multi_value')->default(true);
            $table->boolean('searchable')->default(false);
            $table->timestamps();

            $table->index('position');
        });

        Schema::create('mde_feature_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_family_id')
                ->constrained('mde_feature_families')
                ->cascadeOnDelete();
            $table->string('handle', 64);
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['feature_family_id', 'handle']);
            $table->index(['feature_family_id', 'position']);
        });

        Schema::create('mde_feature_value_product', function (Blueprint $table) use ($prefix): void {
            $table->foreignId('feature_value_id')
                ->constrained('mde_feature_values')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained($prefix.'products')
                ->cascadeOnDelete();

            $table->primary(['feature_value_id', 'product_id']);
            $table->index(['product_id', 'feature_value_id'], 'mde_fvp_product_value_idx');
        });

        Schema::create('mde_feature_family_collection', function (Blueprint $table) use ($prefix): void {
            $table->foreignId('feature_family_id')
                ->constrained('mde_feature_families')
                ->cascadeOnDelete();
            $table->foreignId('collection_id')
                ->constrained($prefix.'collections')
                ->cascadeOnDelete();

            $table->primary(['feature_family_id', 'collection_id']);
            $table->index('collection_id', 'mde_ffc_collection_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mde_feature_family_collection');
        Schema::dropIfExists('mde_feature_value_product');
        Schema::dropIfExists('mde_feature_values');
        Schema::dropIfExists('mde_feature_families');
    }
};
