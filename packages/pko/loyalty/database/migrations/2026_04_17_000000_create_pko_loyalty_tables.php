<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $lunarPrefix = config('lunar.database.table_prefix', 'lunar_');

        Schema::create('pko_loyalty_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('points_required');
            $table->string('gift_title');
            $table->text('gift_description')->nullable();
            $table->string('gift_image_url', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('points_required');
            $table->index('active');
            $table->index('position');
        });

        Schema::create('pko_loyalty_customer_points', function (Blueprint $table) use ($lunarPrefix): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained($lunarPrefix.'customers')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('total_points')->default(0);
            $table->foreignId('current_tier_id')
                ->nullable()
                ->constrained('pko_loyalty_tiers')
                ->nullOnDelete();
            $table->dateTime('last_order_at')->nullable();
            $table->timestamps();

            $table->unique('customer_id');
        });

        Schema::create('pko_loyalty_points_history', function (Blueprint $table) use ($lunarPrefix): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained($lunarPrefix.'customers')
                ->cascadeOnDelete();
            $table->foreignId('order_id')
                ->constrained($lunarPrefix.'orders')
                ->cascadeOnDelete();
            $table->string('order_reference')->nullable();
            $table->unsignedInteger('points_earned');
            $table->unsignedBigInteger('order_total_ht');
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->unique('order_id');
        });

        Schema::create('pko_loyalty_gift_history', function (Blueprint $table) use ($lunarPrefix): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained($lunarPrefix.'customers')
                ->cascadeOnDelete();
            $table->foreignId('tier_id')
                ->constrained('pko_loyalty_tiers')
                ->cascadeOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained($lunarPrefix.'orders')
                ->nullOnDelete();
            $table->unsignedBigInteger('points_at_unlock');
            $table->string('status', 20)->default('pending');
            $table->longText('admin_notes')->nullable();
            $table->boolean('admin_viewed')->default(false);
            $table->boolean('email_sent')->default(false);
            $table->dateTime('unlocked_at');
            $table->dateTime('status_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'tier_id']);
            $table->index('status');
            $table->index('admin_viewed');
        });

        Schema::create('pko_loyalty_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_loyalty_settings');
        Schema::dropIfExists('pko_loyalty_gift_history');
        Schema::dropIfExists('pko_loyalty_points_history');
        Schema::dropIfExists('pko_loyalty_customer_points');
        Schema::dropIfExists('pko_loyalty_tiers');
    }
};
