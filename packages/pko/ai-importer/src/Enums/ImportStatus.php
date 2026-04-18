<?php

declare(strict_types=1);

namespace Pko\AiImporter\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Importing = 'importing';
    case Imported = 'imported';
    case Error = 'error';
    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Scheduled => 'Programmé',
            self::Importing => 'Import en cours',
            self::Imported => 'Importé',
            self::Error => 'Erreur',
            self::RolledBack => 'Rollback effectué',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Scheduled => 'info',
            self::Importing => 'info',
            self::Imported => 'success',
            self::Error => 'danger',
            self::RolledBack => 'warning',
        };
    }
}
