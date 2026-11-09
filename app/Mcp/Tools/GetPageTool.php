<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\DescribesCmsPosts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Pko\StorefrontCms\Models\Post;

/**
 * Lit un contenu CMS complet (métadonnées + arbre `content` du page-builder),
 * pour le modifier ensuite via update_cms_page sans repartir de zéro.
 */
#[IsReadOnly]
class GetPageTool extends Tool
{
    use DescribesCmsPosts;

    protected string $name = 'get_cms_page';

    protected string $description = 'Lit un contenu CMS existant (page/article, brouillon compris) identifié par son id ou son slug : métadonnées, SEO et arbre `content` complet du page-builder { heading, sections[] }. À appeler avant update_cms_page pour modifier une page : partir de ce `content`, le modifier, puis le renvoyer en entier.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id du contenu (voir list_cms_pages). Prioritaire sur slug.'),
            'slug' => $schema->string()->description('Slug du contenu, si l\'id est inconnu.'),
            'post_type' => $schema->string()->description('Handle ou id du type, pour lever l\'ambiguïté d\'un slug présent dans plusieurs types. Optionnel.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $id = (int) $request->get('id');
        $slug = trim((string) $request->get('slug'));
        $type = trim((string) $request->get('post_type'));

        if ($id <= 0 && $slug === '') {
            return Response::error('Fournir `id` ou `slug` (voir list_cms_pages).');
        }

        $query = Post::withoutGlobalScope('pko_api_published_only')->with('postType');

        if ($id > 0) {
            $query->whereKey($id);
        } else {
            $query->where('slug', $slug);
        }

        if ($type !== '') {
            $query->whereHas('postType', fn (Builder $q) => ctype_digit($type)
                ? $q->whereKey((int) $type)
                : $q->where('handle', $type));
        }

        $matches = $query->limit(2)->get();

        if ($matches->isEmpty()) {
            return Response::error($id > 0
                ? "Aucun contenu trouvé pour l'id {$id}."
                : "Aucun contenu trouvé pour le slug « {$slug} ».");
        }

        if ($matches->count() > 1) {
            return Response::error("Le slug « {$slug} » existe dans plusieurs types de contenu : préciser `post_type` ou utiliser l'id.");
        }

        /** @var Post $post */
        $post = $matches->first();

        return Response::json($this->postSummary($post) + [
            'excerpt' => $post->excerpt,
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'cover_url' => $post->cover_url,
            'content' => $post->content ?? ['heading' => null, 'sections' => []],
        ]);
    }
}
