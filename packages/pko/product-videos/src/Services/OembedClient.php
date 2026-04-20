<?php

declare(strict_types=1);

namespace Pko\ProductVideos\Services;

use Illuminate\Support\Facades\Http;
use Pko\ProductVideos\Enums\VideoProvider;

/**
 * Fetches thumbnail URLs via provider oEmbed endpoints. Only used for providers
 * that don't expose a deterministic CDN URL pattern (Vimeo). YouTube and
 * Dailymotion are handled statically by VideoUrlResolver — no network call.
 *
 * Failure is silent (returns null) so UI flows never break because of a
 * flaky provider : a missing thumbnail is acceptable.
 */
final class OembedClient
{
    private const TIMEOUT_SECONDS = 3;

    public function fetchThumbnail(VideoInfo $info): ?string
    {
        $endpoint = $this->endpointFor($info);
        if ($endpoint === null) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($endpoint);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $thumb = $json['thumbnail_url'] ?? null;

        return is_string($thumb) && $thumb !== '' ? $thumb : null;
    }

    private function endpointFor(VideoInfo $info): ?string
    {
        return match ($info->provider) {
            VideoProvider::Vimeo => 'https://vimeo.com/api/oembed.json?url='.urlencode($info->originalUrl),
            default => null,
        };
    }
}
