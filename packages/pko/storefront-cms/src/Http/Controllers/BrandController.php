<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Lunar\Models\Brand;
use Lunar\Models\Url;
use Pko\StorefrontCms\Models\BrandPage;

class BrandController
{
    /** Au-delà, la page liste affiche un index alphabétique (ancres par lettre). */
    public const ALPHA_INDEX_THRESHOLD = 30;

    /**
     * Liste publique des marques ayant au moins un produit publié et
     * accessible (même périmètre que la recherche : storefrontSearchable).
     */
    public function index(): View
    {
        $brands = Brand::query()
            ->whereHas('products', fn ($q) => $q->storefrontSearchable())
            ->with(['media', 'defaultUrl'])
            ->get()
            ->sortBy(fn (Brand $b) => Str::ascii(mb_strtolower((string) $b->name)), SORT_NATURAL)
            ->values();

        // Une marque dont la page builder est vide n'a rien à montrer sur
        // /marque/{slug} (titre seul) : on renvoie vers la recherche filtrée.
        $brandIdsWithPage = BrandPage::query()
            ->whereIn('brand_id', $brands->pluck('id'))
            ->get(['brand_id', 'content'])
            ->filter(fn (BrandPage $p) => ! empty($p->content))
            ->pluck('brand_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $items = $brands->map(fn (Brand $brand) => [
            'brand' => $brand,
            'href' => in_array((int) $brand->id, $brandIdsWithPage, true)
                ? route('brand.view', self::slugFor($brand))
                : route('search.view', ['b' => [$brand->id => 1]]),
            'logo' => pko_media_url(pko_brand_logo($brand), 'small'),
            'letter' => self::letterFor($brand),
        ]);

        return view('storefront-cms::brands.index', [
            'items' => $items,
            'groups' => $items->count() > self::ALPHA_INDEX_THRESHOLD ? $items->groupBy('letter') : null,
        ]);
    }

    /**
     * Slug public d'une marque : URL Lunar par défaut, sinon slug du nom
     * (même résolution que show()).
     */
    public static function slugFor(Brand $brand): string
    {
        $slug = $brand->relationLoaded('defaultUrl') ? $brand->defaultUrl?->slug : $brand->defaultUrl()->first()?->slug;

        return $slug ?: Str::slug((string) $brand->name);
    }

    private static function letterFor(Brand $brand): string
    {
        $first = mb_strtoupper(mb_substr(Str::ascii((string) $brand->name), 0, 1));

        return preg_match('/^[A-Z]$/', $first) === 1 ? $first : '#';
    }

    /**
     * Page publique d'une marque avec contenu builder optionnel.
     */
    public function show(string $slug): View
    {
        // 1. Priorité : Lunar URL (si la marque a une URL publiée via Lunar)
        $brand = null;
        $url = Url::whereElementType((new Brand)->getMorphClass())
            ->whereDefault(true)
            ->whereSlug($slug)
            ->first();

        if ($url !== null) {
            $brand = $url->element;
        }

        // 2. Fallback : match par slug(name) pour éviter de forcer l'utilisateur à créer des Urls.
        if ($brand === null) {
            $brand = Brand::all()->first(fn (Brand $b) => Str::slug($b->name) === $slug);
        }

        abort_unless($brand instanceof Brand, 404);

        $brandPage = BrandPage::firstOrNewForBrand((int) $brand->id);

        $layout = $brandPage->layout && view()->exists($brandPage->layout)
            ? $brandPage->layout
            : 'storefront-cms::brands.show';

        return view($layout, [
            'brand' => $brand,
            'brandPage' => $brandPage,
        ]);
    }
}
