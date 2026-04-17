<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\Product;

class CollectionsIndexPage extends Component
{
    public function render(): View
    {
        $collections = CollectionModel::query()
            ->with(['defaultUrl', 'thumbnail'])
            ->whereIsRoot()
            ->orderBy('_lft')
            ->get();

        $newArrivals = Product::query()
            ->with(['thumbnail', 'brand', 'defaultUrl', 'variants.basePrices'])
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.collections-index', [
            'collections' => $collections,
            'newArrivals' => $newArrivals,
        ]);
    }
}
