<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Pko\PageBuilder\Services\PageBuilderManager;
use Pko\StorefrontCms\Models\PostType;

/**
 * Tool MCP (greffé sur laravel-boost) qui décrit tout le nécessaire pour qu'une
 * IA compose une page CMS : types de contenu, layouts, catalogue des blocs
 * (champs + valeurs autorisées + exemple par bloc) et un exemple de page
 * complète. À appeler AVANT create_cms_page.
 */
#[IsReadOnly]
class PageBuilderCatalogTool extends Tool
{
    protected string $name = 'page_builder_catalog';

    protected string $description = 'Catalogue du page-builder CMS : liste les types de contenu (post types), les layouts de section (1 à 6 colonnes) et TOUS les blocs disponibles avec, pour chacun, ses champs, valeurs autorisées et un exemple. Fournit aussi la forme du contenu et un exemple de page complète. Appelle ce tool avant create_cms_page pour composer une page valide.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        return Response::json([
            'post_types' => PostType::query()
                ->orderBy('sort_order')
                ->get(['id', 'label', 'handle', 'url_segment'])
                ->toArray(),
            'section_layouts' => PageBuilderManager::allowedLayouts(),
            'blocks' => PageBuilderManager::blockCatalog(),
            'content_shape' => '{ "heading": "Titre H1 on-page", "sections": [ { "layout": "1col..6col", "background_color": "#rrggbb|null", "text_color": "#rrggbb|null", "columns": [ { "blocks": [ { "type": "...", ... } ] } ] } ] }',
            'example_page' => [
                'post_type' => PostType::query()->orderBy('sort_order')->value('handle') ?? 'page',
                'title' => 'À propos de nous',
                'status' => 'draft',
                'content' => PageBuilderManager::exampleContent(),
            ],
            'notes' => [
                'Le H1 de la page est le champ content.heading (pas un bloc). Il peut différer du "title" (nom en base) pour le SEO.',
                'Le nombre de colonnes du tableau "columns" doit correspondre au layout (1col=1 … 6col=6). Sinon il est tronqué/complété automatiquement.',
                'Les blocs image/galerie référencent des médias EXISTANTS via media_id / media_ids. Une image peut aussi utiliser une URL absolue.',
                'Les pages créées sont en brouillon (draft) par défaut : elles doivent être publiées depuis le back-office.',
            ],
        ]);
    }
}
