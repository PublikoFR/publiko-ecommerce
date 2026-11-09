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
 * Liste les contenus CMS existants (pages/articles, brouillons inclus) avec
 * filtres et pagination. Le contenu complet se lit ensuite via get_cms_page.
 */
#[IsReadOnly]
class ListPagesTool extends Tool
{
    use DescribesCmsPosts;

    private const MAX_PER_PAGE = 100;

    protected string $name = 'list_cms_pages';

    protected string $description = 'Liste les contenus CMS existants (pages et articles, brouillons compris), du plus récemment modifié au plus ancien. Filtres optionnels : type de contenu, statut, recherche texte sur le titre/slug. Paginé. Renvoie pour chaque contenu son id, type, titre, slug, statut, dates, nombre de blocs et URLs. Pour lire le contenu complet d\'une page, appeler ensuite get_cms_page avec son id.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_type' => $schema->string()->description('Filtrer par type de contenu : handle (ex. "page", "article") ou id. Voir page_builder_catalog.post_types. Optionnel.'),
            'status' => $schema->string()->enum(['all', 'draft', 'published'])->description('Filtrer par statut. Défaut : "all".'),
            'search' => $schema->string()->description('Texte recherché dans le titre ou le slug. Optionnel.'),
            'page' => $schema->integer()->description('Numéro de page (1 par défaut).'),
            'per_page' => $schema->integer()->description('Résultats par page (20 par défaut, 100 max).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $page = max(1, (int) ($request->get('page') ?: 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($request->get('per_page') ?: 20)));
        $status = (string) ($request->get('status') ?: 'all');
        $type = trim((string) $request->get('post_type'));
        $search = trim((string) $request->get('search'));

        $query = Post::withoutGlobalScope('pko_api_published_only')->with('postType');

        if ($type !== '') {
            $query->whereHas('postType', fn (Builder $q) => ctype_digit($type)
                ? $q->whereKey((int) $type)
                : $q->where('handle', $type));
        }

        if (in_array($status, ['draft', 'published'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('slug', 'like', $like));
        }

        $paginator = $query->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);

        return Response::json([
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
            'items' => collect($paginator->items())->map(fn (Post $post): array => $this->postSummary($post))->all(),
        ]);
    }
}
