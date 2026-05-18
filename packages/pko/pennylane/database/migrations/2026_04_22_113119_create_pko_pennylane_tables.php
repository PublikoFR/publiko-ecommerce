<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_pennylane_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lunar_customer_id')
                ->unique()
                ->constrained('lunar_customers')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('pennylane_customer_id')->nullable()->index();
            $table->string('external_reference')->unique();
            $table->json('payload_snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pko_pennylane_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('lunar_orders')
                ->nullOnDelete();
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('lunar_transactions')
                ->nullOnDelete();
            $table->foreignId('parent_invoice_id')
                ->nullable()
                ->constrained('pko_pennylane_invoices')
                ->nullOnDelete();
            $table->enum('type', ['invoice', 'credit_note'])->default('invoice');
            $table->unsignedBigInteger('pennylane_id')->nullable()->unique();
            $table->string('pennylane_invoice_number')->nullable()->index();
            $table->string('external_reference')->unique();
            $table->enum('status', ['pending', 'draft', 'finalized', 'failed'])->default('pending');
            $table->text('last_error')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_pennylane_invoices');
        Schema::dropIfExists('pko_pennylane_customers');
    }
};
