<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Models\Collection;
use Lunar\Models\Product;

class HomeFeaturedProducts extends Component
{
    public function render(): View
    {
        $slug = (string) config('storefront.home.featured_collection_slug', '');
        $products = collect();

        if ($slug !== '') {
            $collection = Collection::query()->whereHas('urls', fn ($q) => $q->where('slug', $slug))->first();
            if ($collection) {
                $products = $collection->products()->with(['thumbnail', 'brand', 'defaultUrl', 'variants.basePrices'])->limit(6)->get();
            }
        }

        if ($products->isEmpty()) {
            $products = Product::query()
                ->with(['thumbnail', 'brand', 'defaultUrl', 'variants.basePrices'])
                ->latest()
                ->limit(6)
                ->get();
        }

        return view('storefront-cms::livewire.home-featured', ['products' => $products]);
    }
}
