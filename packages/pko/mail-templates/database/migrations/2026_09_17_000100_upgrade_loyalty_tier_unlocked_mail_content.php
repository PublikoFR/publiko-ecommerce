<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Pko\MailTemplates\Support\DefaultContentUpgrade;

/**
 * Encart cadeau dans l'e-mail « Nouveau palier » : applique le nouveau contenu
 * par défaut aux bases où le texte n'a pas été retouché en back-office.
 */
return new class extends Migration
{
    public function up(): void
    {
        DefaultContentUpgrade::applyFile(
            __DIR__.'/../content/upgrades/2026_09_17_loyalty_tier_unlocked_gift_card.php'
        );
    }

    public function down(): void
    {
        // Pas de retour arrière : l'ancien texte reste disponible dans le fichier d'upgrade.
    }
};
