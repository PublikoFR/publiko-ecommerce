<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_quote_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('mode', 32)->index();
            $table->string('status', 32)->default('new')->index();

            // Coordonnées saisies (copiées même si client connecté : la demande
            // reste lisible si le compte est modifié ou anonymisé plus tard).
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('email', 180);
            $table->string('phone', 40)->nullable();
            $table->string('postcode', 20)->nullable();

            $table->text('message');
            $table->json('lines')->nullable();

            // Contexte d'origine (CTA cliqué).
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('collection', 190)->nullable();
            $table->string('from_url', 500)->nullable();
            $table->json('context')->nullable();

            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('ip', 45)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pko_quote_request_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_request_id')
                ->constrained('pko_quote_requests')
                ->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_quote_request_files');
        Schema::dropIfExists('pko_quote_requests');
    }
};
