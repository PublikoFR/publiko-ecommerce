<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_customers', function (Blueprint $table): void {
            // Défaut 'active' = client non encore géré par le statut PKO (grandfathered).
            // Les nouvelles inscriptions front posent explicitement 'pending' via RegisterProCustomer.
            $table->string('pko_status', 16)->default('active')->after('naf_code')->index();
            $table->string('pko_street')->nullable()->after('pko_status');
            $table->string('pko_postcode', 10)->nullable()->after('pko_street');
            $table->string('pko_city', 100)->nullable()->after('pko_postcode');
            $table->string('pko_country', 2)->nullable()->default('FR')->after('pko_city');
            $table->boolean('sepa_enabled')->default(false)->after('pko_country');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_customers', function (Blueprint $table): void {
            $table->dropIndex(['pko_status']);
            $table->dropColumn(['pko_status', 'pko_street', 'pko_postcode', 'pko_city', 'pko_country', 'sepa_enabled']);
        });
    }
};
