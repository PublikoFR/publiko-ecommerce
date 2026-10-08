<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bons de livraison Pennylane (commercial documents `shipping_order`).
 *
 * Table séparée de `pko_pennylane_invoices` : tout ce qui lit cette table
 * (e-mail de facture, espace client, liste admin, polling) suppose un document
 * de facturation. Un bon de livraison y serait envoyé au client comme facture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_pennylane_delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')
                ->nullable()
                ->unique()
                ->constrained('lunar_orders')
                ->nullOnDelete();
            $table->unsignedBigInteger('pennylane_id')->nullable()->unique();
            $table->string('pennylane_document_number')->nullable()->index();
            $table->string('external_reference')->unique();
            $table->enum('status', ['pending', 'finalized', 'failed'])->default('pending');
            $table->text('last_error')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_pennylane_delivery_notes');
    }
};
