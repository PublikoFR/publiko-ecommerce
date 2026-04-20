<?php

declare(strict_types=1);

namespace Pko\ProductVideos\Services;

use Pko\ProductVideos\Enums\VideoProvider;

final class VideoInfo
{
    public function __construct(
        public readonly VideoProvider $provider,
        public readonly ?string $videoId,
        public readonly string $embedUrl,
        public readonly ?string $thumbnailUrl,
        public readonly string $originalUrl,
    ) {}
}
