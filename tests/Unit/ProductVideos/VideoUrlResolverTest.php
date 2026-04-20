<?php

declare(strict_types=1);

namespace Tests\Unit\ProductVideos;

use PHPUnit\Framework\TestCase;
use Pko\ProductVideos\Enums\VideoProvider;
use Pko\ProductVideos\Exceptions\UnsupportedVideoUrlException;
use Pko\ProductVideos\Services\VideoUrlResolver;

final class VideoUrlResolverTest extends TestCase
{
    private VideoUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new VideoUrlResolver;
    }

    /** @dataProvider youtubeUrls */
    public function test_detects_youtube_formats(string $url, string $expectedId): void
    {
        $info = $this->resolver->resolve($url);

        $this->assertSame(VideoProvider::YouTube, $info->provider);
        $this->assertSame($expectedId, $info->videoId);
        $this->assertSame("https://www.youtube.com/embed/{$expectedId}", $info->embedUrl);
        $this->assertSame("https://img.youtube.com/vi/{$expectedId}/hqdefault.jpg", $info->thumbnailUrl);
    }

    public static function youtubeUrls(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch short domain' => ['https://youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'shorts' => ['https://www.youtube.com/shorts/abcDEF12345', 'abcDEF12345'],
            'watch with extra params' => ['https://www.youtube.com/watch?foo=bar&v=dQw4w9WgXcQ&t=10', 'dQw4w9WgXcQ'],
        ];
    }

    /** @dataProvider vimeoUrls */
    public function test_detects_vimeo_formats(string $url, string $expectedId): void
    {
        $info = $this->resolver->resolve($url);

        $this->assertSame(VideoProvider::Vimeo, $info->provider);
        $this->assertSame($expectedId, $info->videoId);
        $this->assertSame("https://player.vimeo.com/video/{$expectedId}", $info->embedUrl);
    }

    public static function vimeoUrls(): array
    {
        return [
            'public' => ['https://vimeo.com/123456789', '123456789'],
            'www' => ['https://www.vimeo.com/123456789', '123456789'],
            'player' => ['https://player.vimeo.com/video/123456789', '123456789'],
        ];
    }

    /** @dataProvider dailymotionUrls */
    public function test_detects_dailymotion_formats(string $url, string $expectedId): void
    {
        $info = $this->resolver->resolve($url);

        $this->assertSame(VideoProvider::Dailymotion, $info->provider);
        $this->assertSame($expectedId, $info->videoId);
        $this->assertSame("https://www.dailymotion.com/embed/video/{$expectedId}", $info->embedUrl);
    }

    public static function dailymotionUrls(): array
    {
        return [
            'full' => ['https://www.dailymotion.com/video/x7tgad0', 'x7tgad0'],
            'no-www' => ['https://dailymotion.com/video/x7tgad0', 'x7tgad0'],
            'short' => ['https://dai.ly/x7tgad0', 'x7tgad0'],
        ];
    }

    /** @dataProvider mp4Urls */
    public function test_detects_mp4(string $url): void
    {
        $info = $this->resolver->resolve($url);

        $this->assertSame(VideoProvider::Mp4, $info->provider);
        $this->assertNull($info->videoId);
        $this->assertSame($url, $info->embedUrl);
        $this->assertNull($info->thumbnailUrl);
    }

    public static function mp4Urls(): array
    {
        return [
            'plain' => ['https://cdn.example.com/videos/demo.mp4'],
            'with query' => ['https://cdn.example.com/videos/demo.mp4?v=1'],
            'http' => ['http://cdn.example.com/demo.mp4'],
        ];
    }

    public function test_throws_for_unsupported_url(): void
    {
        $this->expectException(UnsupportedVideoUrlException::class);
        $this->resolver->resolve('https://example.com/not-a-video');
    }

    public function test_try_resolve_returns_null_for_unsupported(): void
    {
        $this->assertNull($this->resolver->tryResolve('https://example.com/not-a-video'));
    }
}
