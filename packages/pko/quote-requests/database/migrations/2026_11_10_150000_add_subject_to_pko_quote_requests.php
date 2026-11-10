<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Objet saisi dans le formulaire de contact classique : ses messages sont
 * désormais enregistrés dans pko_quote_requests (mode `contact`) pour être
 * suivis dans la page admin « Contacts ». Null pour les demandes de devis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_quote_requests', function (Blueprint $table): void {
            $table->string('subject', 160)->nullable()->after('postcode');
        });
    }

    public function down(): void
    {
        Schema::table('pko_quote_requests', function (Blueprint $table): void {
            $table->dropColumn('subject');
        });
    }
};
