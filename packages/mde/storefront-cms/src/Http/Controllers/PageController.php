<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Mde\StorefrontCms\Models\Page;

class PageController
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('storefront-cms::pages.show', ['page' => $page]);
    }
}
