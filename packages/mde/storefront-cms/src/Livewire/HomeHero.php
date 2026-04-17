<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Mde\StorefrontCms\Models\HomeSlide;

class HomeHero extends Component
{
    public function render(): View
    {
        $slides = Cache::remember('mde.home.slides.v1', 900, fn () => HomeSlide::active()->get());

        return view('storefront-cms::livewire.home-hero', ['slides' => $slides]);
    }
}
