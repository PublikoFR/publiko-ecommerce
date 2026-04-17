<?php

declare(strict_types=1);

namespace Mde\AiImporter\Enums;

enum ErrorPolicy: string
{
    case Ignore = 'ignore';
    case Stop = 'stop';
    case Rollback = 'rollback';

    public function label(): string
    {
        return match ($this) {
            self::Ignore => 'Ignorer et continuer',
            self::Stop => 'Arrêter l\'import',
            self::Rollback => 'Rollback complet',
        };
    }
}
