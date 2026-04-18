<?php

declare(strict_types=1);

namespace Pko\AiImporter\Enums;

enum StagingStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Created = 'created';
    case Updated = 'updated';
    case Imported = 'imported';
    case Error = 'error';
    case Skipped = 'skipped';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À valider',
            self::Validated => 'Validé',
            self::Created => 'Créé',
            self::Updated => 'Mis à jour',
            self::Imported => 'Importé',
            self::Error => 'Erreur',
            self::Skipped => 'Ignoré',
            self::Warning => 'Avertissement',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Validated, self::Imported, self::Created, self::Updated => 'success',
            self::Warning => 'warning',
            self::Error => 'danger',
            self::Skipped => 'info',
        };
    }
}
