<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Pko\StorefrontCms\Models\Page;

class PageController
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('storefront-cms::pages.show', ['page' => $page]);
    }
}
