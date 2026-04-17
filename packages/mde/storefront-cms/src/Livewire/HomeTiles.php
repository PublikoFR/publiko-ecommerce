<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Mde\StorefrontCms\Models\HomeTile;

class HomeTiles extends Component
{
    public function render(): View
    {
        $tiles = Cache::remember('mde.home.tiles.v1', 900, fn () => HomeTile::active()->get());

        return view('storefront-cms::livewire.home-tiles', ['tiles' => $tiles]);
    }
}
