<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_negotiated_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('lunar_customers')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('lunar_product_variants')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('lunar_currencies')->cascadeOnDelete();
            // Prix HT en centimes (entier), cohérent avec le stockage des prix Lunar.
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->unique(
                ['customer_id', 'product_variant_id', 'currency_id'],
                'pko_negotiated_prices_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_negotiated_prices');
    }
};
