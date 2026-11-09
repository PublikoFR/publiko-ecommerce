<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\PageBuilderCatalogTool;
use App\Mcp\Tools\UpdatePageTool;
use Laravel\Mcp\Server;

/**
 * Serveur MCP HTTP (streamable) exposant les tools de gestion de contenu CMS,
 * ajouté comme connecteur custom dans claude.ai ou ChatGPT. Auth OAuth 2.1
 * (laravel/mcp + Passport, cf. routes/ai.php). Réutilise les mêmes tools que
 * le MCP local laravel-boost → logique partagée (PageComposer), aucun drift.
 */
class PageBuilderMcpServer extends Server
{
    protected string $name = 'Contenus CMS';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Ce serveur permet de composer, créer, modifier et publier des pages/articles
        du site avec le page-builder (blocs).

        Toujours commencer par `page_builder_catalog` : types de contenu, layouts
        (1 à 6 colonnes), blocs disponibles (champs, valeurs autorisées, exemples)
        et un exemple de page complète.

        Consulter l'existant :
        - `list_cms_pages` liste les pages/articles (filtres type, statut,
          recherche ; paginé) ;
        - `get_cms_page` lit une page complète (métadonnées + `content`).

        Créer : composer un `content` = { heading, sections: [ { layout, columns:
        [ { blocks: [...] } ] } ] } conforme au catalogue, puis `create_cms_page`
        (brouillon par défaut).

        Modifier : `get_cms_page`, modifier son `content`, puis `update_cms_page`
        avec le `content` COMPLET (il remplace l'ancien). Ne fournir que les
        autres champs à changer. Publier = `update_cms_page` status="published".
        MARKDOWN;

    /**
     * @var array<int, class-string>
     */
    protected array $tools = [
        PageBuilderCatalogTool::class,
        ListPagesTool::class,
        GetPageTool::class,
        CreatePageTool::class,
        UpdatePageTool::class,
    ];

    /**
     * Nom affiché dans le client MCP : marque lue depuis les Settings (jamais
     * de nom de boutique en dur).
     */
    protected function boot(): void
    {
        $this->name = brand_name().' — Contenus CMS';
    }
}
