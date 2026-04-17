<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mde_purchase_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('lunar_customers')->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('customer_id');
        });

        Schema::create('mde_purchase_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_list_id')->constrained('mde_purchase_lists')->cascadeOnDelete();
            $table->morphs('purchasable');
            $table->unsignedInteger('quantity')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mde_purchase_list_items');
        Schema::dropIfExists('mde_purchase_lists');
    }
};
