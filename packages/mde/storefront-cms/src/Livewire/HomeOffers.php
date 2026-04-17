<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Mde\StorefrontCms\Models\HomeOffer;

class HomeOffers extends Component
{
    public function render(): View
    {
        $offers = Cache::remember('mde.home.offers.v1', 900, fn () => HomeOffer::active()->get());

        return view('storefront-cms::livewire.home-offers', ['offers' => $offers]);
    }
}
