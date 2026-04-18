<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Pko\StorefrontCms\Models\Post;

class HomePosts extends Component
{
    public function render(): View
    {
        $posts = Cache::remember('pko.home.posts.v1', 900, fn () => Post::published()->limit(4)->get());

        return view('storefront-cms::livewire.home-posts', ['posts' => $posts]);
    }
}
