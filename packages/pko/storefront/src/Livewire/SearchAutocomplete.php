<?php

declare(strict_types=1);

namespace Pko\Storefront\Livewire;

use Illuminate\Support\Facades\DB;
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
        return redirect('/recherche?q='.urlencode($this->term));
    }

    public function render(): View
    {
        $products = collect();
        $brands = collect();
        $collections = collect();

        if (strlen($this->term) >= 2) {
            $like = '%'.$this->term.'%';

            $products = Product::query()
                ->with(['thumbnail', 'brand', 'defaultUrl', 'variants'])
                ->whereExists(function ($q) use ($like) {
                    $q->from('lunar_product_translations as t')
                        ->whereColumn('t.product_id', 'lunar_products.id')
                        ->where('t.name', 'like', $like);
                })
                ->orWhereHas('variants', fn ($q) => $q->where('sku', 'like', $like))
                ->limit(8)
                ->get();

            if ($products->isEmpty()) {
                $products = Product::query()
                    ->with(['thumbnail', 'brand', 'defaultUrl', 'variants'])
                    ->whereHas('variants', fn ($q) => $q->where('sku', 'like', $like))
                    ->limit(8)
                    ->get();
            }

            $brands = Brand::query()->where('name', 'like', $like)->limit(3)->get();
            $collections = Collection::query()->with('defaultUrl')->whereExists(function ($q) use ($like) {
                $q->from(DB::raw('(SELECT 1)'))->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(attribute_data, '$.name.value')) LIKE ?", [$like]);
            })->limit(3)->get();
        }

        return view('storefront::livewire.search-autocomplete', [
            'products' => $products,
            'brands' => $brands,
            'collections' => $collections,
        ]);
    }
}
