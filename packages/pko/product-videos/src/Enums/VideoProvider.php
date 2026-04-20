<?php

declare(strict_types=1);

namespace Pko\ProductVideos\Enums;

enum VideoProvider: string
{
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';
    case Dailymotion = 'dailymotion';
    case Mp4 = 'mp4';

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Vimeo => 'Vimeo',
            self::Dailymotion => 'Dailymotion',
            self::Mp4 => 'MP4',
        };
    }

    public function isIframe(): bool
    {
        return $this !== self::Mp4;
    }
}
