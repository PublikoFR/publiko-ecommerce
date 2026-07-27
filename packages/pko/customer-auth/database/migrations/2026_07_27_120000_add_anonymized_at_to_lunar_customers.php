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
            // Horodatage d'anonymisation RGPD. Renseigné quand une fiche client est
            // anonymisée (données perso effacées, commandes conservées). Sert à masquer
            // ces fiches de la liste admin par défaut (filtre « afficher les anonymisés »).
            $table->timestamp('anonymized_at')->nullable()->after('sepa_enabled')->index();
        });
    }

    public function down(): void
    {
        Schema::table('lunar_customers', function (Blueprint $table): void {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn('anonymized_at');
        });
    }
};
