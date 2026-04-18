<?php

declare(strict_types=1);

namespace App\Generators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\Generators\UrlGenerator;
use Lunar\Models\Product;

/**
 * Product URL generator — slug format: `{brand}-{name}-{mpn}`.
 *
 * - Brand : `$product->brand->name` (nullable).
 * - Name  : `$product->translateAttribute('name')` (Lunar attribute_data).
 * - MPN   : `$product->variants()->first()->mpn` — manufacturer reference.
 *           Only included when a single variant exists (branded catalogue items).
 *           Custom / made-to-measure products (menuiseries) have multiple
 *           variants without a shared MPN → slug falls back to `brand-name`.
 *
 * Other HasUrls models (Collection, Brand) fall through to Lunar's default
 * UrlGenerator behaviour.
 */
class PkoProductUrlGenerator extends UrlGenerator
{
    public function handle(Model $model): void
    {
        if (! $model instanceof Product) {
            parent::handle($model);

            return;
        }

        $this->regenerate($model);
    }

    /**
     * Compute the desired slug and, if it differs from the current default URL,
     * create a new default URL for the product. Old URL is auto-demoted by Lunar
     * → SEO history preserved.
     *
     * Called on Product::saved and ProductVariant::saved hooks to handle the
     * case where the MPN only becomes available after variants are created.
     */
    public function regenerate(Product $product): void
    {
        $this->model = $product;

        $parts = array_filter([
            $this->brandSlugPart($product),
            $this->nameSlugPart($product),
            $this->mpnSlugPart($product),
        ], fn ($s) => $s !== '' && $s !== null);

        if (empty($parts)) {
            return;
        }

        $desired = Str::slug(implode(' ', $parts));

        $current = $product->urls()
            ->where('language_id', $this->defaultLanguage->id)
            ->where('default', true)
            ->value('slug');

        if ($current === $desired) {
            return;
        }

        $this->createUrl($desired);
    }

    private function brandSlugPart(Product $product): ?string
    {
        return $product->brand?->name ? Str::slug((string) $product->brand->name) : null;
    }

    private function nameSlugPart(Product $product): ?string
    {
        $name = $product->translateAttribute('name');

        return $name ? Str::slug((string) $name) : null;
    }

    private function mpnSlugPart(Product $product): ?string
    {
        $variants = $product->variants;

        if ($variants->count() !== 1) {
            return null;
        }

        $mpn = $variants->first()->mpn;

        return $mpn ? Str::slug((string) $mpn) : null;
    }
}
