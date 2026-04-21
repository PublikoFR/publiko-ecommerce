<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;

class PostController
{
    /**
     * Liste des contenus d'un post type donné (ex: /article, /guide).
     */
    public function index(string $postTypeSegment): View
    {
        $postType = PostType::where('url_segment', $postTypeSegment)->firstOrFail();
        $posts = Post::published()
            ->where('post_type_id', $postType->id)
            ->paginate(12);

        return view('storefront-cms::posts.index', [
            'posts' => $posts,
            'postType' => $postType,
        ]);
    }

    /**
     * Affichage d'un contenu (ex: /article/mon-article, /guide/comment-faire).
     * Layout résolu via PostType->layout avec fallback storefront-cms::posts.show.
     */
    public function show(string $postTypeSegment, string $slug): View
    {
        $postType = PostType::where('url_segment', $postTypeSegment)->firstOrFail();
        $post = Post::published()
            ->where('post_type_id', $postType->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $layout = $postType->layout && view()->exists($postType->layout)
            ? $postType->layout
            : 'storefront-cms::posts.show';

        return view($layout, [
            'post' => $post,
            'postType' => $postType,
        ]);
    }

    /**
     * Redirects 301 des anciennes URLs /actualites/{slug} et /pages/{slug}
     * vers les nouvelles URLs /{post_type_segment}/{slug}.
     */
    public function legacyArticleRedirect(string $slug): RedirectResponse
    {
        $articleType = PostType::where('handle', 'article')->firstOrFail();

        return redirect("/{$articleType->url_segment}/{$slug}", 301);
    }

    public function legacyPageRedirect(string $slug): RedirectResponse
    {
        $pageType = PostType::where('handle', 'page')->firstOrFail();

        return redirect("/{$pageType->url_segment}/{$slug}", 301);
    }
}
