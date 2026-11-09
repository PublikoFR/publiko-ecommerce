<?php

declare(strict_types=1);

namespace Pko\AiImporter\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Importing = 'importing';
    case Imported = 'imported';
    case Error = 'error';
    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À lancer',
            self::Scheduled => 'Programmé',
            self::Queued => 'En file d\'attente',
            self::Importing => 'Import en cours',
            self::Imported => 'Importé',
            self::Error => 'Erreur',
            self::RolledBack => 'Annulé (restauré)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Scheduled => 'info',
            self::Queued => 'info',
            self::Importing => 'info',
            self::Imported => 'success',
            self::Error => 'danger',
            self::RolledBack => 'warning',
        };
    }
}
