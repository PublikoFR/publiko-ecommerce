<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Mde\StorefrontCms\Models\Post;

class PostController
{
    public function index(): View
    {
        $posts = Post::published()->paginate(12);

        return view('storefront-cms::posts.index', ['posts' => $posts]);
    }

    public function show(string $slug): View
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        return view('storefront-cms::posts.show', ['post' => $post]);
    }
}
