<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Lunar\Models\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Downloads product images from remote URLs into the Lunar media collection
 * (Spatie MediaLibrary, `config('lunar.media.collection')`).
 *
 * Idempotent: each imported `Media` row stores its original URL under
 * `custom_properties.source_url` so that re-running an import does not
 * re-download already present images. The first URL of the list is flagged
 * `primary=true` (that's the flag Lunar uses to pick the thumbnail).
 *
 * Videos (YouTube / Vimeo) — v1 only stores the raw URLs on
 * `Product::attribute_data['videos']` as a plain array. A dedicated custom
 * table will come later if we need richer per-video metadata (title,
 * thumbnail override, sort order, provider-specific options).
 */
final class ProductImagePipeline
{
    private readonly string $collectionName;

    public function __construct()
    {
        $this->collectionName = (string) config('lunar.media.collection', 'images');
    }

    /**
     * @param  string|array<int, string>|null  $urls
     * @return array{added:int, skipped:int, errors:int}
     */
    public function syncImages(Product $product, mixed $urls): array
    {
        $list = $this->normaliseUrls($urls);
        if ($list === []) {
            return ['added' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $existing = $product->getMedia($this->collectionName)
            ->mapWithKeys(fn (Media $m): array => [(string) $m->getCustomProperty('source_url') => $m]);

        $added = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($list as $i => $url) {
            if ($existing->has($url)) {
                $skipped++;

                continue;
            }
            try {
                $product->addMediaFromUrl($url)
                    ->withCustomProperties([
                        'source_url' => $url,
                        'primary' => $i === 0 && $existing->isEmpty(),
                    ])
                    ->toMediaCollection($this->collectionName);
                $added++;
            } catch (\Throwable $e) {
                $errors++;
                report($e);
            }
        }

        return compact('added', 'skipped', 'errors');
    }

    /**
     * @param  array<int, string>|string|null  $videos
     * @return array<int, string> urls stored on Product::attribute_data['videos']
     */
    public function stashVideoUrls(mixed $videos): array
    {
        return $this->normaliseUrls($videos);
    }

    /**
     * @return array<int, string>
     */
    private function normaliseUrls(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', array_map('strval', $raw)),
            static fn (string $u): bool => $u !== '' && filter_var($u, FILTER_VALIDATE_URL) !== false,
        ));
    }
}
