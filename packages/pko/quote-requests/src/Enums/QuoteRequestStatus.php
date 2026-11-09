<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Enums;

enum QuoteRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouvelle',
            self::InProgress => 'En cours',
            self::Done => 'Traitée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
