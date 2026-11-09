<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Support;

use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\QuoteRequests\Enums\QuoteRequestMode;

/**
 * Génère l'URL du formulaire de devis avec son contexte d'origine.
 *
 * ```php
 * quote_request_url('part_photo');
 * quote_request_url('general', ['product' => $variant, 'from' => request()->getRequestUri()]);
 * ```
 *
 * `product` accepte une variante (→ id), un produit (→ slug), un id ou un slug.
 * Les valeurs vides sont omises de la query string.
 */
final class QuoteRequestUrl
{
    /** Chemin public du formulaire (route `quote-request`). */
    public const PATH = '/demande-de-devis';

    /**
     * @param  array{product?: mixed, ref?: string|null, collection?: string|null, from?: string|null}  $context
     */
    public static function make(QuoteRequestMode|string $mode = QuoteRequestMode::General, array $context = [], bool $absolute = false): string
    {
        $mode = $mode instanceof QuoteRequestMode ? $mode : QuoteRequestMode::fromQuery($mode);

        $query = array_filter([
            'mode' => $mode === QuoteRequestMode::General ? null : $mode->value,
            'product' => self::productParam($context['product'] ?? null),
            'ref' => $context['ref'] ?? null,
            'collection' => $context['collection'] ?? null,
            'from' => isset($context['from']) ? QuoteContext::internalPath($context['from']) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $base = app('router')->has('quote-request')
            ? route('quote-request', [], $absolute)
            : self::PATH;

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }

    private static function productParam(mixed $product): ?string
    {
        return match (true) {
            $product instanceof ProductVariant => (string) $product->id,
            $product instanceof Product => $product->defaultUrl?->slug ?? null,
            is_int($product) => (string) $product,
            is_string($product) && $product !== '' => $product,
            default => null,
        };
    }
}
