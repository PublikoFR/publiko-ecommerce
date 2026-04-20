<?php

declare(strict_types=1);

namespace Pko\ProductVideos\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Pko\ProductVideos\Enums\VideoProvider;
use Pko\ProductVideos\Models\ProductVideo;
use Pko\ProductVideos\Services\VideoInfo;
use Pko\ProductVideos\Services\VideoUrlResolver;

/**
 * Renders a single product video in a 16:9 responsive frame.
 * Accepts either a ProductVideo model OR a raw URL.
 */
class VideoEmbed extends Component
{
    public VideoInfo $info;

    public ?string $title;

    public function __construct(
        ?ProductVideo $video = null,
        ?string $url = null,
        ?string $title = null,
    ) {
        if ($video === null && $url === null) {
            throw new \InvalidArgumentException('x-pko-product-video requires either :video or :url.');
        }

        $resolver = app(VideoUrlResolver::class);
        $this->info = $video !== null
            ? new VideoInfo(
                provider: $video->provider,
                videoId: $video->provider_video_id,
                embedUrl: $this->embedUrlFor($video, $resolver),
                thumbnailUrl: null,
                originalUrl: $video->url,
            )
            : $resolver->resolve((string) $url);

        $this->title = $title ?? $video?->title;
    }

    public function render(): View
    {
        return view('product-videos::components.video-embed');
    }

    public function isIframe(): bool
    {
        return $this->info->provider !== VideoProvider::Mp4;
    }

    private function embedUrlFor(ProductVideo $video, VideoUrlResolver $resolver): string
    {
        // Re-run the resolver on the stored URL so we always emit a canonical
        // embed URL (cheaper than storing it, keeps the table lean).
        $info = $resolver->tryResolve($video->url);

        return $info?->embedUrl ?? $video->url;
    }
}
