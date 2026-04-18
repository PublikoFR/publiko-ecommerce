<?php

declare(strict_types=1);

namespace Pko\Loyalty\Enums;

enum GiftStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('loyalty::status.pending'),
            self::Processing => __('loyalty::status.processing'),
            self::Sent => __('loyalty::status.sent'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
