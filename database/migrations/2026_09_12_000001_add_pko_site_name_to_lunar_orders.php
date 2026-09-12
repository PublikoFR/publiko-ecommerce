<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table): void {
            $table->string('pko_site_name')->nullable()->after('customer_reference');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table): void {
            $table->dropColumn('pko_site_name');
        });
    }
};
