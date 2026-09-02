<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

/**
 * Levée par `MailTemplate::saving()` quand `ContentGuard` refuse le contenu.
 * Filet de sécurité au niveau modèle : couvre tout chemin d'écriture, y
 * compris ceux qui ne passent pas par `EditMailTemplate` (éditeur PageBuilder,
 * seeder, tinker).
 */
final class ContentGuardException extends \RuntimeException {}
