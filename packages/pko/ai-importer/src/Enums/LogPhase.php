<?php

declare(strict_types=1);

namespace Pko\AiImporter\Enums;

/**
 * Phase d'un job qui a produit un log : la préparation (parse du fichier vers
 * le staging) ou l'import (écriture du staging dans Lunar, sauvegarde/restauration).
 */
enum LogPhase: string
{
    case Parse = 'parse';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Parse => 'Préparation',
            self::Import => 'Import',
        };
    }
}
