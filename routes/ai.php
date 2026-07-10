<?php

declare(strict_types=1);

use App\Mcp\Servers\PageBuilderMcpServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * Serveurs MCP HTTP (streamable), configurables comme connecteurs custom dans
 * claude.ai — PKOS en hérite automatiquement.
 *
 * Auth = OAuth 2.1 (DCR + PKCE) géré par laravel/mcp + Laravel Passport :
 * claude.ai s'enregistre (DCR), fait le consent (le personnel back-office
 * s'authentifie), reçoit un access token, puis appelle en `Authorization: Bearer`.
 * Le token n'est JAMAIS dans l'URL. Le guard `auth:api` (Passport, provider
 * oauth_staff) protège le endpoint.
 */

// Endpoints de découverte OAuth (.well-known/*) + enregistrement dynamique (DCR).
Mcp::oauthRoutes();

// Serveur MCP "Contenus CMS" : catalogue de blocs + créer / modifier / publier.
Mcp::web('/mcp/page-builder', PageBuilderMcpServer::class)
    ->middleware('auth:api');
