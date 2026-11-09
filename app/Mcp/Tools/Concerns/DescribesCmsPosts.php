<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Services\PageComposer;
use Throwable;

/**
 * Représentation commune d'un contenu CMS dans les réponses des tools MCP
 * (liste, lecture, création, modification) → mêmes clés partout.
 */
trait DescribesCmsPosts
{
    /**
     * @return array<string, mixed>
     */
    protected function postSummary(Post $post): array
    {
        return [
            'id' => $post->getKey(),
            'post_type' => $post->postType?->handle,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'blocks_count' => $this->countBlocks($post),
            'public_url' => app(PageComposer::class)->publicUrl($post),
            'admin_edit_url' => $this->adminUrl($post),
        ];
    }

    protected function countBlocks(Post $post): int
    {
        $count = 0;
        foreach (($post->content['sections'] ?? []) as $section) {
            foreach (($section['columns'] ?? []) as $column) {
                $count += count($column['blocks'] ?? []);
            }
        }

        return $count;
    }

    protected function adminUrl(Post $post): ?string
    {
        try {
            // Passer le MODÈLE (pas la clé) : le back-office route par slug
            // (Post::getRouteKeyName() = 'slug') → Filament utilise getRouteKey().
            return PostResource::getUrl('edit', ['record' => $post]);
        } catch (Throwable) {
            return null;
        }
    }
}
