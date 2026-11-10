<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versements de BFA (bonus de fin d'année) : une ligne par tranche, c.-à-d.
 * par déblocage d'un palier BFA (`gift_history_id`). Pré-calculée en fin
 * d'année, ajustable par l'admin, puis émise en avoir comptable.
 *
 * `gift_history_id` est nullable (nullOnDelete) : un avoir déjà émis survit à
 * la suppression de son déblocage ; un brouillon orphelin est purgé au
 * pré-calcul suivant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lunarPrefix = config('lunar.database.table_prefix', 'lunar_');

        Schema::create('pko_loyalty_bfa_payouts', function (Blueprint $table) use ($lunarPrefix): void {
            $table->id();
            $table->foreignId('gift_history_id')
                ->nullable()
                ->unique()
                ->constrained('pko_loyalty_gift_history')
                ->nullOnDelete();
            $table->foreignId('customer_id')
                ->constrained($lunarPrefix.'customers')
                ->cascadeOnDelete();
            $table->foreignId('tier_id')
                ->nullable()
                ->constrained('pko_loyalty_tiers')
                ->nullOnDelete();
            $table->string('tier_name', 100)->nullable();
            $table->unsignedSmallInteger('year');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->unsignedBigInteger('revenue_ht')->default(0);
            $table->unsignedInteger('fixed_amount')->default(0);
            $table->decimal('percent', 5, 2)->default(0);
            $table->unsignedBigInteger('computed_amount')->default(0);
            $table->unsignedBigInteger('adjusted_amount')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('external_reference')->unique();
            $table->unsignedBigInteger('pennylane_id')->nullable();
            $table->string('credit_note_number')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->timestamps();

            $table->index(['year', 'status']);
            $table->index(['customer_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_loyalty_bfa_payouts');
    }
};
