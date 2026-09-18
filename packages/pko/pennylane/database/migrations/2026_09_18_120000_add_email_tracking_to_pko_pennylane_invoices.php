<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_pennylane_invoices', function (Blueprint $table): void {
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('email_claimed_at')->nullable();
            $table->uuid('email_claim_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pko_pennylane_invoices', function (Blueprint $table): void {
            $table->dropColumn(['emailed_at', 'email_claimed_at', 'email_claim_token']);
        });
    }
};
