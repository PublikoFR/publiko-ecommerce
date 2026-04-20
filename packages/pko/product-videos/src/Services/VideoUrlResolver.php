<?php

declare(strict_types=1);

namespace Pko\ProductVideos\Services;

use Pko\ProductVideos\Enums\VideoProvider;
use Pko\ProductVideos\Exceptions\UnsupportedVideoUrlException;

/**
 * Detects the provider (YouTube, Vimeo, Dailymotion, MP4) of a video URL and
 * returns a VideoInfo with the canonical embed URL + thumbnail (when derivable
 * from the URL alone, no network call).
 *
 * Supported formats :
 *   - YouTube : youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID,
 *               youtube.com/shorts/ID (accepts http/https and optional www.)
 *   - Vimeo   : vimeo.com/ID, player.vimeo.com/video/ID
 *   - Dailymotion : dailymotion.com/video/ID, dai.ly/ID
 *   - MP4     : any URL whose path ends with `.mp4` (query string allowed)
 */
final class VideoUrlResolver
{
    public function resolve(string $url): VideoInfo
    {
        $trimmed = trim($url);

        if ($info = $this->tryYouTube($trimmed)) {
            return $info;
        }
        if ($info = $this->tryVimeo($trimmed)) {
            return $info;
        }
        if ($info = $this->tryDailymotion($trimmed)) {
            return $info;
        }
        if ($info = $this->tryMp4($trimmed)) {
            return $info;
        }

        throw UnsupportedVideoUrlException::for($trimmed);
    }

    public function tryResolve(string $url): ?VideoInfo
    {
        try {
            return $this->resolve($url);
        } catch (UnsupportedVideoUrlException) {
            return null;
        }
    }

    private function tryYouTube(string $url): ?VideoInfo
    {
        $patterns = [
            '#^https?://(?:www\.)?youtube\.com/watch\?(?:.*&)?v=([\w-]{6,})#i',
            '#^https?://(?:www\.)?youtube\.com/embed/([\w-]{6,})#i',
            '#^https?://(?:www\.)?youtube\.com/shorts/([\w-]{6,})#i',
            '#^https?://youtu\.be/([\w-]{6,})#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                $id = $matches[1];

                return new VideoInfo(
                    provider: VideoProvider::YouTube,
                    videoId: $id,
                    embedUrl: "https://www.youtube.com/embed/{$id}",
                    thumbnailUrl: "https://img.youtube.com/vi/{$id}/hqdefault.jpg",
                    originalUrl: $url,
                );
            }
        }

        return null;
    }

    private function tryVimeo(string $url): ?VideoInfo
    {
        $patterns = [
            '#^https?://(?:www\.)?vimeo\.com/(\d+)#i',
            '#^https?://player\.vimeo\.com/video/(\d+)#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                $id = $matches[1];

                return new VideoInfo(
                    provider: VideoProvider::Vimeo,
                    videoId: $id,
                    embedUrl: "https://player.vimeo.com/video/{$id}",
                    thumbnailUrl: null, // Vimeo requires an API call to get a thumbnail URL
                    originalUrl: $url,
                );
            }
        }

        return null;
    }

    private function tryDailymotion(string $url): ?VideoInfo
    {
        $patterns = [
            '#^https?://(?:www\.)?dailymotion\.com/video/([a-z0-9]+)#i',
            '#^https?://dai\.ly/([a-z0-9]+)#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                $id = $matches[1];

                return new VideoInfo(
                    provider: VideoProvider::Dailymotion,
                    videoId: $id,
                    embedUrl: "https://www.dailymotion.com/embed/video/{$id}",
                    thumbnailUrl: "https://www.dailymotion.com/thumbnail/video/{$id}",
                    originalUrl: $url,
                );
            }
        }

        return null;
    }

    private function tryMp4(string $url): ?VideoInfo
    {
        // Path ends with .mp4, query string allowed.
        if (preg_match('#^https?://.+\.mp4(?:\?.*)?$#i', $url) !== 1) {
            return null;
        }

        return new VideoInfo(
            provider: VideoProvider::Mp4,
            videoId: null,
            embedUrl: $url,
            thumbnailUrl: null,
            originalUrl: $url,
        );
    }
}
