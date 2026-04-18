<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_customers', function (Blueprint $table) {
            $table->string('sirene_status', 16)->nullable()->after('meta')->index();
            $table->timestamp('sirene_verified_at')->nullable()->after('sirene_status');
            $table->string('naf_code', 8)->nullable()->after('sirene_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_customers', function (Blueprint $table) {
            $table->dropColumn(['sirene_status', 'sirene_verified_at', 'naf_code']);
        });
    }
};
