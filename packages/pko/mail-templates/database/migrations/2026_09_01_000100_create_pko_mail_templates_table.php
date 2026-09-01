<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_mail_templates', function (Blueprint $table) {
            $table->id();
            // Clé technique stable (ex. `order.confirmed`) : c'est elle que le code
            // appelle, jamais l'id. Un renommage de libellé ne casse donc aucun envoi.
            $table->string('key')->unique();
            $table->string('locale', 5)->default('fr');
            $table->string('subject');
            // Liste ordonnée de blocs typés (paragraph, heading, button…). JSON plutôt
            // qu'un champ HTML libre : l'éditeur back-office ne peut pas casser la
            // structure du mail ni injecter de markup arbitraire.
            $table->json('blocks');
            // Un mail désactivé n'est jamais envoyé, même si son déclencheur se produit.
            // Sert aux mails dont le contenu client n'est pas encore arrivé (07, 08)
            // et à ceux dont le module métier n'existe pas (14, 15).
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_mail_templates');
    }
};
