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

    /**
     * Met à jour un contenu existant. Seules les clés présentes dans $data sont
     * modifiées (patch partiel). "content" est re-normalisé, le slug re-vérifié
     * unique. Publier = passer status à "published".
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post
    {
        if (array_key_exists('post_type', $data) && $data['post_type'] !== null && $data['post_type'] !== '') {
            $post->post_type_id = $this->resolvePostType($data['post_type'])->id;
        }

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw new InvalidArgumentException('Le champ "title" ne peut pas être vide.');
            }
            $post->title = $title;
        }

        if (array_key_exists('slug', $data) && trim((string) $data['slug']) !== '') {
            $post->slug = $this->uniqueSlug((string) $data['slug'], (int) $post->post_type_id, $post->getKey());
        }

        foreach (['excerpt', 'seo_title', 'seo_description'] as $field) {
            if (array_key_exists($field, $data)) {
                $post->{$field} = $data[$field] !== null ? (string) $data[$field] : null;
            }
        }

        if (array_key_exists('status', $data) && in_array($data['status'], ['draft', 'published'], true)) {
            $post->status = (string) $data['status'];
            if ($post->status === 'published' && $post->published_at === null) {
                $post->published_at = now();
            }
        }

        if (array_key_exists('published_at', $data)) {
            $post->published_at = $this->resolvePublishedAt($post->status, $data['published_at']);
        }

        if (array_key_exists('content', $data) && is_array($data['content'])) {
            $content = $data['content'];
            if (isset($data['heading']) && is_string($data['heading']) && trim($data['heading']) !== '') {
                $content['heading'] = $data['heading'];
            }
            $post->content = PageBuilderManager::normalize($content);
        } elseif (array_key_exists('heading', $data) && is_string($data['heading'])) {
            // Modifier seulement le H1 on-page sans toucher aux sections.
            $tree = PageBuilderManager::normalize(is_array($post->content) ? $post->content : []);
            $tree['heading'] = $data['heading'];
            $post->content = PageBuilderManager::normalize($tree);
        }

        $post->save();

        if (array_key_exists('cover_media_id', $data) && method_exists($post, 'syncMediaAttachments')) {
            $ids = $data['cover_media_id'] ? [(int) $data['cover_media_id']] : [];
            $post->syncMediaAttachments($ids, 'cover');
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

    private function uniqueSlug(string $base, int $postTypeId, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'page';
        $candidate = $slug;
        $i = 2;
        while (Post::query()
            ->where('post_type_id', $postTypeId)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
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
