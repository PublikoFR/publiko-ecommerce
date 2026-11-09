<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Pko\MailTemplates\Support\DefaultContentUpgrade;

/**
 * Validation manuelle des comptes pro (FEAT-006) : les e-mails de bienvenue,
 * « compte validé » et la notification équipe ne promettent plus un accès
 * ouvert par la seule vérification de l'e-mail. Applique le nouveau contenu
 * par défaut aux bases où le texte n'a pas été retouché en back-office.
 */
return new class extends Migration
{
    public function up(): void
    {
        DefaultContentUpgrade::applyFile(
            __DIR__.'/../content/upgrades/2026_11_09_account_manual_validation.php'
        );
    }

    public function down(): void
    {
        // Pas de retour arrière : l'ancien texte reste disponible dans le fichier d'upgrade.
    }
};
