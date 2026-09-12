<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_mail_templates', function (Blueprint $table) {
            // Réglages spécifiques par modèle (ex. délai de relance en jours
            // pour `cart.abandoned`). JSON extensible sans nouvelle migration.
            $table->json('settings')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('pko_mail_templates', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
