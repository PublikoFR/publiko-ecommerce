<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_mail_dispatch_log', function (Blueprint $table) {
            $table->id();
            $table->string('mail_key');
            // Entité déclenchante (commande, client, panier…), en polymorphe léger :
            // le socle ne doit dépendre d'aucun modèle métier en particulier.
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('recipient');
            $table->timestamp('sent_at');

            // Le tuple complet, destinataire inclus : un même e-mail peut
            // légitimement partir à deux adresses différentes pour une même
            // commande (client + donneur d'ordre), mais jamais deux fois à la même.
            $table->unique(
                ['mail_key', 'entity_type', 'entity_id', 'recipient'],
                'pko_mail_dispatch_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_mail_dispatch_log');
    }
};
