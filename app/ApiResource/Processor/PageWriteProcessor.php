<?php

declare(strict_types=1);

namespace App\ApiResource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Services\PageComposer;

/**
 * State processor des opérations d'écriture (POST/PATCH) de la ressource Post
 * dans API Platform. Ne fait JAMAIS confiance à l'objet hydraté par API Platform
 * (risque de mass-assignment) : il en extrait une liste blanche de champs et
 * délègue à PageComposer (création = record neuf ; édition = copie fraîche
 * rechargée par id, hors global scope). Le contenu est normalisé/assaini.
 *
 * @implements ProcessorInterface<Post, Post>
 */
final class PageWriteProcessor implements ProcessorInterface
{
    public function __construct(private readonly PageComposer $composer) {}

    /**
     * @param  Post  $data
     * @param  array<string, mixed>  $uriVariables
     * @param  array<string, mixed>  $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Post
    {
        $payload = $this->whitelist($data);

        $id = $data->getKey() ?? ($uriVariables['id'] ?? null);

        if ($id !== null) {
            $post = Post::withoutGlobalScope('pko_api_published_only')->findOrFail($id);

            return $this->composer->update($post, $payload);
        }

        return $this->composer->create($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function whitelist(Post $d): array
    {
        // Type de contenu : soit la relation `postType` (IRI, voie API Platform
        // standard), soit un scalaire `post_type` (handle ou id) du corps brut —
        // plus simple pour une IA. On tolère aussi la clé camelCase.
        $postType = $d->post_type_id
            ?? request()->input('post_type')
            ?? request()->input('postType');

        return [
            'post_type' => $postType,
            'title' => $d->title,
            'slug' => $d->slug,
            'status' => $d->status,
            'excerpt' => $d->excerpt,
            'seo_title' => $d->seo_title,
            'seo_description' => $d->seo_description,
            'published_at' => $d->published_at?->toIso8601String(),
            'content' => is_array($d->content) ? $d->content : [],
        ];
    }
}
