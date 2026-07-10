<?php

declare(strict_types=1);
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\PageBuilderCatalogTool;

/*
 * Config Laravel Boost applicative. Fusionnée par-dessus les défauts du package
 * (mergeConfigFrom) : on n'ajoute ici que la clé `mcp.tools.include` pour greffer
 * nos tools custom sur le serveur MCP existant (boost:mcp) — sans créer d'API
 * séparée. Les autres réglages (enabled, browser_logs_watcher, executable_paths)
 * restent ceux du package.
 */

return [

    'mcp' => [
        'tools' => [
            // Tools custom exposés à l'IA en plus des tools natifs de Boost.
            'include' => [
                PageBuilderCatalogTool::class,
                CreatePageTool::class,
            ],
        ],
    ],

];
