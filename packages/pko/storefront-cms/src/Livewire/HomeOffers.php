<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Pko\StorefrontCms\Models\HomeOffer;

class HomeOffers extends Component
{
    public function render(): View
    {
        $offers = Cache::remember('pko.home.offers.v1', 900, fn () => HomeOffer::active()->get());

        return view('storefront-cms::livewire.home-offers', ['offers' => $offers]);
    }
}
