<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Pko\StorefrontCms\Models\HomeTile;

class HomeTiles extends Component
{
    public function render(): View
    {
        $tiles = Cache::remember('pko.home.tiles.v1', 900, fn () => HomeTile::active()->get());

        return view('storefront-cms::livewire.home-tiles', ['tiles' => $tiles]);
    }
}
