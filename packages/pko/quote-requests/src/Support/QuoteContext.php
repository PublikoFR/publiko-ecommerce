<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Support;

use Illuminate\Support\Str;
use Lunar\Models\Collection;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\Url;

/**
 * Contexte d'origine d'une demande de devis, reconstruit depuis la query string
 * (`product`, `ref`, `collection`, `from`).
 *
 * Tout est re-résolu côté serveur : un id de variante ou un slug inexistant est
 * ignoré, une URL `from` externe est écartée (pas de redirection ouverte, pas
 * de lien piégé dans l'e-mail équipe).
 */
final class QuoteContext
{
    /**
     * @param  array<string, mixed>  $query
     * @return array{product_id: int|null, product_variant_id: int|null, product_name: string|null, product_url: string|null, reference: string|null, collection: string|null, collection_name: string|null, from: string|null}
     */
    public static function fromQuery(array $query): array
    {
        $variant = null;
        $product = null;
        $rawProduct = self::string($query['product'] ?? null, 190);

        if ($rawProduct !== null) {
            if (ctype_digit($rawProduct)) {
                $variant = ProductVariant::modelClass()::query()->with('product')->find((int) $rawProduct);
                $product = $variant?->product;
            } else {
                $product = self::productBySlug($rawProduct);
            }
        }

        $reference = self::string($query['ref'] ?? null, 120);
        if ($reference === null && $variant instanceof ProductVariant && is_string($variant->sku) && $variant->sku !== '') {
            $reference = $variant->sku;
        }

        $collectionSlug = self::string($query['collection'] ?? null, 190);

        return [
            'product_id' => $product?->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product ? self::productName($product) : null,
            'product_url' => $product ? self::productUrl($product) : null,
            'reference' => $reference,
            'collection' => $collectionSlug,
            'collection_name' => $collectionSlug ? self::collectionName($collectionSlug) : null,
            'from' => self::internalPath($query['from'] ?? null),
        ];
    }

    /** Vrai si le contexte porte une information à afficher. */
    public static function isFilled(array $context): bool
    {
        return ($context['product_name'] ?? null) !== null
            || ($context['reference'] ?? null) !== null
            || ($context['collection_name'] ?? $context['collection'] ?? null) !== null;
    }

    /**
     * Chemin interne uniquement : « /… » (pas « //hote »), ou URL absolue du
     * même hôte que l'application, ramenée à son chemin.
     */
    public static function internalPath(mixed $value): ?string
    {
        $value = self::string($value, 500);
        if ($value === null || preg_match('/[\x00-\x1F\x7F\\\\]/', $value) === 1) {
            return null;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        $parts = parse_url($value);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = request()->getHost();

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if ($host === null || ! in_array($host, array_filter([$appHost, $requestHost]), true)) {
            return null;
        }

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return str_starts_with($path, '/') ? Str::limit($path, 500, '') : null;
    }

    private static function productBySlug(string $slug): ?Product
    {
        $url = Url::query()
            ->where('slug', $slug)
            ->where('element_type', (new (Product::modelClass()))->getMorphClass())
            ->first();

        $element = $url?->element;

        return $element instanceof Product ? $element : null;
    }

    private static function productName(Product $product): ?string
    {
        $name = $product->translateAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    private static function productUrl(Product $product): ?string
    {
        $slug = $product->defaultUrl?->slug;

        return is_string($slug) && $slug !== '' && app('router')->has('product.view')
            ? route('product.view', $slug, false)
            : null;
    }

    private static function collectionName(string $slug): ?string
    {
        $url = Url::query()
            ->where('slug', $slug)
            ->where('element_type', (new (Collection::modelClass()))->getMorphClass())
            ->first();

        $element = $url?->element;
        if (! $element instanceof Collection) {
            return null;
        }

        $name = $element->translateAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    private static function string(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
