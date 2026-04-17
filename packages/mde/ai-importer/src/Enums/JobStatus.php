<?php

declare(strict_types=1);

namespace Mde\AiImporter\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case Paused = 'paused';
    case Parsed = 'parsed';
    case Error = 'error';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Parsing => 'Parsing…',
            self::Paused => 'En pause',
            self::Parsed => 'Parsé',
            self::Error => 'Erreur',
            self::Cancelled => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Paused => 'gray',
            self::Parsing => 'info',
            self::Parsed => 'success',
            self::Error => 'danger',
            self::Cancelled => 'warning',
        };
    }
}
