<?php

declare(strict_types=1);

namespace Tests\Feature\ProductVideos;

use Illuminate\Support\Facades\Http;
use Pko\ProductVideos\Enums\VideoProvider;
use Pko\ProductVideos\Services\OembedClient;
use Pko\ProductVideos\Services\VideoInfo;
use Tests\TestCase;

class OembedClientTest extends TestCase
{
    public function test_fetches_vimeo_thumbnail_via_oembed(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response([
                'thumbnail_url' => 'https://i.vimeocdn.com/video/1234567890-abc.jpg',
                'title' => 'Sample',
            ]),
        ]);

        $info = new VideoInfo(
            provider: VideoProvider::Vimeo,
            videoId: '524933864',
            embedUrl: 'https://player.vimeo.com/video/524933864',
            thumbnailUrl: null,
            originalUrl: 'https://vimeo.com/524933864',
        );

        $thumb = app(OembedClient::class)->fetchThumbnail($info);

        $this->assertSame('https://i.vimeocdn.com/video/1234567890-abc.jpg', $thumb);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'vimeo.com/api/oembed.json')
            && str_contains($req->url(), urlencode('https://vimeo.com/524933864')));
    }

    public function test_returns_null_for_youtube(): void
    {
        // YouTube thumbnails are deterministic — no oEmbed call needed.
        Http::fake();

        $info = new VideoInfo(
            provider: VideoProvider::YouTube,
            videoId: 'abc',
            embedUrl: 'https://www.youtube.com/embed/abc',
            thumbnailUrl: 'https://img.youtube.com/vi/abc/hqdefault.jpg',
            originalUrl: 'https://youtu.be/abc',
        );

        $this->assertNull(app(OembedClient::class)->fetchThumbnail($info));
        Http::assertNothingSent();
    }

    public function test_returns_null_on_http_error(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response('', 404),
        ]);

        $info = new VideoInfo(
            provider: VideoProvider::Vimeo,
            videoId: '999',
            embedUrl: 'https://player.vimeo.com/video/999',
            thumbnailUrl: null,
            originalUrl: 'https://vimeo.com/999',
        );

        $this->assertNull(app(OembedClient::class)->fetchThumbnail($info));
    }

    public function test_returns_null_on_network_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('timeout');
        });

        $info = new VideoInfo(
            provider: VideoProvider::Vimeo,
            videoId: '999',
            embedUrl: 'https://player.vimeo.com/video/999',
            thumbnailUrl: null,
            originalUrl: 'https://vimeo.com/999',
        );

        $this->assertNull(app(OembedClient::class)->fetchThumbnail($info));
    }
}
