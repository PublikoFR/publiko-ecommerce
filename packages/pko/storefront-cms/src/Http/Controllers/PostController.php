<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Pko\StorefrontCms\Models\Post;

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
