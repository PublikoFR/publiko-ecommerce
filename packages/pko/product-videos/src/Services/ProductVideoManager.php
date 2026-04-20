<?php

declare(strict_types=1);

namespace Pko\ProductVideos\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Pko\ProductVideos\Exceptions\UnsupportedVideoUrlException;
use Pko\ProductVideos\Models\ProductVideo;

/**
 * Public entry-point for managing product videos. Any consumer (admin UI,
 * ai-importer, CLI command) should go through this class rather than touching
 * ProductVideo directly — unicity, ordering and provider detection are
 * centralized here.
 */
final class ProductVideoManager
{
    public function __construct(
        private readonly VideoUrlResolver $resolver,
    ) {}

    public function exists(Product $product, string $url): bool
    {
        return ProductVideo::query()
            ->where('product_id', $product->id)
            ->where('url', trim($url))
            ->exists();
    }

    /**
     * Insert a video if the URL is not already attached to the product.
     * Returns null if the video already exists, the new ProductVideo otherwise.
     *
     * @throws UnsupportedVideoUrlException when the URL is not recognized
     */
    public function addIfNotExists(Product $product, string $url, ?string $title = null): ?ProductVideo
    {
        if ($this->exists($product, $url)) {
            return null;
        }

        return $this->add($product, $url, $title);
    }

    /**
     * @throws UnsupportedVideoUrlException when the URL is not recognized
     */
    public function add(Product $product, string $url, ?string $title = null): ProductVideo
    {
        $info = $this->resolver->resolve($url);

        $nextSortOrder = (int) ProductVideo::query()
            ->where('product_id', $product->id)
            ->max('sort_order');

        return ProductVideo::query()->create([
            'product_id' => $product->id,
            'url' => trim($url),
            'provider' => $info->provider,
            'provider_video_id' => $info->videoId,
            'title' => $title,
            'sort_order' => $nextSortOrder + 1,
        ]);
    }

    /**
     * Synchronize a batch of URLs (idempotent). Existing URLs are skipped,
     * invalid URLs are counted as errors. No deletion of previously existing
     * videos — call delete() explicitly for that.
     *
     * @param  array<int, string>  $urls
     * @return array{added:int, skipped:int, errors:int}
     */
    public function sync(Product $product, array $urls): array
    {
        $added = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($urls as $raw) {
            $url = trim((string) $raw);
            if ($url === '') {
                continue;
            }
            try {
                $result = $this->addIfNotExists($product, $url);
                if ($result === null) {
                    $skipped++;
                } else {
                    $added++;
                }
            } catch (UnsupportedVideoUrlException) {
                $errors++;
            }
        }

        return compact('added', 'skipped', 'errors');
    }

    /**
     * Rewrite sort_order for the given product according to the id list.
     *
     * @param  array<int, int|string>  $idsInOrder
     */
    public function reorder(Product $product, array $idsInOrder): void
    {
        DB::transaction(function () use ($product, $idsInOrder): void {
            foreach (array_values($idsInOrder) as $position => $id) {
                ProductVideo::query()
                    ->where('product_id', $product->id)
                    ->whereKey((int) $id)
                    ->update(['sort_order' => $position + 1]);
            }
        });
    }

    public function delete(ProductVideo $video): void
    {
        $video->delete();
    }
}
