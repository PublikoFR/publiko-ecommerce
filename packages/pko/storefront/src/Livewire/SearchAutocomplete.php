<?php

declare(strict_types=1);

namespace Pko\Storefront\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\Product;

class SearchAutocomplete extends Component
{
    public string $term = '';

    public bool $open = false;

    public function updatedTerm(): void
    {
        $this->open = strlen($this->term) >= 2;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function submitSearch(): mixed
    {
        return redirect('/recherche?term='.urlencode($this->term));
    }

    public function render(): View
    {
        $products = collect();
        $brands = collect();
        $collections = collect();

        if (strlen($this->term) >= 2) {
            $like = '%'.mb_strtolower($this->term).'%';

            $products = Product::query()
                ->with(['thumbnail', 'brand', 'defaultUrl', 'variants'])
                ->storefrontSearchable()
                // Couverture texte partagée (nom/description/short_description/sku/ean/mpn/gtin/tags).
                ->storefrontSearchMatch($this->term)
                ->limit(10)
                ->get();

            $brands = Brand::query()->whereRaw('LOWER(name) LIKE ?', [$like])->limit(3)->get();
            $collections = Collection::query()
                ->with('defaultUrl')
                ->navVisible()
                ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(lunar_collections.attribute_data, '$.name.value'))) LIKE ?", [$like])
                ->limit(3)
                ->get();
        }

        return view('storefront::livewire.search-autocomplete', [
            'products' => $products,
            'brands' => $brands,
            'collections' => $collections,
        ]);
    }
}
