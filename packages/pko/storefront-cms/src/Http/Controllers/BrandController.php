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
