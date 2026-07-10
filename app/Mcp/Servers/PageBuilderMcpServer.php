<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\PageBuilderCatalogTool;
use App\Mcp\Tools\UpdatePageTool;
use Laravel\Mcp\Server;

/**
 * Serveur MCP HTTP (streamable) exposant les tools de gestion de contenu CMS,
 * pensé pour être ajouté comme connecteur custom dans claude.ai (PKOS en hérite
 * automatiquement). L'auth se fait par un secret en query string sur l'URL du
 * connecteur (claude.ai n'accepte pas de header Bearer dans l'UI des connecteurs
 * custom) — cf. middleware EnsureMcpSecret. Réutilise les mêmes tools que le MCP
 * local laravel-boost → logique partagée (PageComposer), aucun drift.
 */
class PageBuilderMcpServer extends Server
{
    protected string $name = 'Weklo — Contenus CMS';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Ce serveur permet de composer, créer, modifier et publier des pages/articles
        du site avec le page-builder (blocs).

        Workflow recommandé :
        1. Appeler `page_builder_catalog` pour connaître les types de contenu, les
           layouts (1 à 6 colonnes) et tous les blocs disponibles (champs, valeurs
           autorisées, exemples) + un exemple de page complète.
        2. Composer un objet `content` = { heading, sections: [ { layout, columns:
           [ { blocks: [...] } ] } ] } conforme au catalogue.
        3. Créer la page avec `create_cms_page` (brouillon par défaut).
        4. La publier ou la modifier avec `update_cms_page` (status="published").
        MARKDOWN;

    /**
     * @var array<int, class-string>
     */
    protected array $tools = [
        PageBuilderCatalogTool::class,
        CreatePageTool::class,
        UpdatePageTool::class,
    ];
}
