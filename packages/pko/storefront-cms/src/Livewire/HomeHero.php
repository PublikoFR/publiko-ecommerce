<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Pko\StorefrontCms\Models\HomeSlide;

class HomeHero extends Component
{
    public function render(): View
    {
        $slides = Cache::remember('pko.home.slides.v1', 900, fn () => HomeSlide::active()->get());

        return view('storefront-cms::livewire.home-hero', ['slides' => $slides]);
    }
}
