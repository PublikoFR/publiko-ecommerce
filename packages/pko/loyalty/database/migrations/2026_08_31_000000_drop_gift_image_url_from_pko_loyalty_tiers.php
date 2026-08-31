<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_loyalty_tiers', function (Blueprint $table): void {
            $table->dropColumn('gift_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('pko_loyalty_tiers', function (Blueprint $table): void {
            $table->string('gift_image_url', 500)->nullable();
        });
    }
};
