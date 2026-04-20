<?php

declare(strict_types=1);

namespace Pko\ProductVideos\Exceptions;

use RuntimeException;

class UnsupportedVideoUrlException extends RuntimeException
{
    public static function for(string $url): self
    {
        return new self(sprintf(
            'URL vidéo non supportée : %s. Providers gérés : YouTube, Vimeo, Dailymotion, MP4 direct.',
            $url,
        ));
    }
}
