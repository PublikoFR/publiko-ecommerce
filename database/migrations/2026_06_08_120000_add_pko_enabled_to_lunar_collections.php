<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_collections', function (Blueprint $table): void {
            $table->boolean('pko_enabled')->default(true)->after('sort');
            $table->index('pko_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_collections', function (Blueprint $table): void {
            $table->dropIndex(['pko_enabled']);
            $table->dropColumn('pko_enabled');
        });
    }
};
