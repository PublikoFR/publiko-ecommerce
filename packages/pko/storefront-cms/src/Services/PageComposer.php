<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Pko\PageBuilder\Services\PageBuilderManager;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;

/**
 * Crée un contenu CMS (Post = page ou article) à partir d'un payload simple et
 * tolérant, pensé pour une IA. Toute la validation/normalisation du contenu
 * page-builder passe par PageBuilderManager::normalize (jamais de throw sur le
 * contenu : les blocs inconnus sont droppés, les valeurs bornées). Les seules
 * erreurs levées concernent les métadonnées obligatoires (type, titre).
 *
 * Réutilisable par n'importe quelle surface (tool MCP boost, futur endpoint…).
 */
final class PageComposer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Post
    {
        $type = $this->resolvePostType($data['post_type'] ?? null);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Le champ "title" est obligatoire.');
        }

        $status = in_array($data['status'] ?? null, ['draft', 'published'], true)
            ? (string) $data['status']
            : 'draft';

        $content = $data['content'] ?? [];
        if (! is_array($content)) {
            throw new InvalidArgumentException('Le champ "content" doit être un objet { "heading"?: string, "sections": [...] }.');
        }
        // H1 on-page : param `heading` prioritaire, sinon celui du content, sinon le titre.
        if (isset($data['heading']) && is_string($data['heading']) && trim($data['heading']) !== '') {
            $content['heading'] = $data['heading'];
        }
        if (! isset($content['heading']) || trim((string) $content['heading']) === '') {
            $content['heading'] = $title;
        }

        $post = new Post;
        $post->post_type_id = $type->id;
        $post->title = $title;
        $post->slug = $this->uniqueSlug((string) ($data['slug'] ?? $title), (int) $type->id);
        $post->excerpt = isset($data['excerpt']) ? (string) $data['excerpt'] : null;
        $post->status = $status;
        $post->published_at = $this->resolvePublishedAt($status, $data['published_at'] ?? null);
        $post->seo_title = isset($data['seo_title']) ? (string) $data['seo_title'] : null;
        $post->seo_description = isset($data['seo_description']) ? (string) $data['seo_description'] : null;
        $post->content = PageBuilderManager::normalize($content);
        $post->save();

        if (! empty($data['cover_media_id']) && method_exists($post, 'syncMediaAttachments')) {
            $post->syncMediaAttachments([(int) $data['cover_media_id']], 'cover');
        }

        return $post;
    }

    private function resolvePostType(mixed $ref): PostType
    {
        if ($ref === null || $ref === '') {
            throw new InvalidArgumentException('Le champ "post_type" est obligatoire (handle ou id, ex. "page" ou "article").');
        }

        $type = is_numeric($ref)
            ? PostType::query()->find((int) $ref)
            : PostType::query()->where('handle', (string) $ref)->first();

        if (! $type) {
            $available = PostType::query()->orderBy('sort_order')->pluck('handle')->implode(', ');

            throw new InvalidArgumentException("Type de contenu \"{$ref}\" introuvable. Disponibles : {$available}.");
        }

        return $type;
    }

    private function uniqueSlug(string $base, int $postTypeId): string
    {
        $slug = Str::slug($base) ?: 'page';
        $candidate = $slug;
        $i = 2;
        while (Post::query()->where('post_type_id', $postTypeId)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private function resolvePublishedAt(string $status, mixed $raw): ?Carbon
    {
        if (is_string($raw) && trim($raw) !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // ignore une date invalide
            }
        }

        return $status === 'published' ? now() : null;
    }

    /** URL publique du contenu (segment du type + slug). */
    public function publicUrl(Post $post): string
    {
        $segment = $post->postType?->url_segment ?? 'page';

        return url('/'.trim($segment, '/').'/'.$post->slug);
    }
}
