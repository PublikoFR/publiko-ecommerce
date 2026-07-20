<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_customer_groups', function (Blueprint $table): void {
            // Groupe "métier" : proposé dans la liste déroulante du formulaire
            // d'inscription pour attribuer un métier au nouveau client.
            $table->boolean('pko_is_metier')->default(false)->after('default');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_customer_groups', function (Blueprint $table): void {
            $table->dropColumn('pko_is_metier');
        });
    }
};
