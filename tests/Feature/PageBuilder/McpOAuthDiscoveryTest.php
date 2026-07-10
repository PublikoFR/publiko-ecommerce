<?php

declare(strict_types=1);

namespace Tests\Feature\PageBuilder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie le scaffolding OAuth du serveur MCP HTTP (connecteur claude.ai) :
 * endpoints de découverte servis, endpoint MCP protégé (401 sans token). Le
 * flow complet DCR + PKCE + authorize + token se valide contre claude.ai.
 */
class McpOAuthDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_resource_metadata_is_served(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/page-builder')
            ->assertOk()
            ->assertJsonStructure(['resource', 'authorization_servers']);
    }

    public function test_authorization_server_metadata_is_served(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonStructure([
                'issuer',
                'authorization_endpoint',
                'token_endpoint',
                'registration_endpoint',
            ]);
    }

    public function test_mcp_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/mcp/page-builder', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $response->assertStatus(401);
        // WWW-Authenticate doit pointer vers la resource metadata (découverte OAuth).
        $this->assertNotEmpty($response->headers->get('WWW-Authenticate'));
    }
}
