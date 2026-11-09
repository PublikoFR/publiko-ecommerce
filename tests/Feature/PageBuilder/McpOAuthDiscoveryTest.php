<?php

declare(strict_types=1);

namespace Tests\Feature\PageBuilder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Lunar\Admin\Models\Staff;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

/**
 * Vérifie le scaffolding OAuth du serveur MCP HTTP (connecteurs claude.ai et
 * ChatGPT) : découverte, endpoint MCP protégé (401 sans token), enregistrement
 * dynamique (DCR) limité aux domaines autorisés, et écran de consentement
 * réservé au staff (redirection vers la connexion back-office sinon).
 */
class McpOAuthDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private const CHATGPT_REDIRECT = 'https://chatgpt.com/connector_platform_oauth_redirect';

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

    public function test_chatgpt_and_claude_can_register_a_client(): void
    {
        foreach ([self::CHATGPT_REDIRECT, 'https://claude.ai/api/mcp/auth_callback'] as $redirect) {
            $this->postJson('/oauth/register', [
                'client_name' => 'Connecteur',
                'redirect_uris' => [$redirect],
            ])
                ->assertOk()
                ->assertJsonPath('redirect_uris', [$redirect])
                ->assertJsonPath('token_endpoint_auth_method', 'none');
        }
    }

    public function test_unknown_redirect_domain_is_rejected(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Intrus',
            'redirect_uris' => ['https://chatgpt.com.evil.example/callback'],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_guest_is_sent_to_back_office_login_for_consent(): void
    {
        $this->useTemporaryPassportKeys();

        $this->get($this->authorizeUrl($this->registerChatGptClient()))
            ->assertRedirect(route('filament.lunar.auth.login'));
    }

    public function test_guest_reaches_back_office_login_even_during_maintenance(): void
    {
        $this->useTemporaryPassportKeys();
        Setting::set('storefront.maintenance', true);

        $this->get($this->authorizeUrl($this->registerChatGptClient()))
            ->assertRedirect(route('filament.lunar.auth.login'));
    }

    public function test_customer_area_guest_still_goes_to_customer_login(): void
    {
        // La redirection back-office est limitée à /oauth/* : l'espace client
        // continue d'envoyer vers la connexion client.
        $this->get(route('account.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_logged_in_staff_sees_consent_screen(): void
    {
        $this->useTemporaryPassportKeys();
        $clientId = $this->registerChatGptClient();

        $this->actingAs(Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'staff-oauth@example.test',
            'password' => 'secret123',
            'admin' => true,
        ]), 'staff');

        $this->get($this->authorizeUrl($clientId))
            ->assertOk()
            ->assertSee('ChatGPT');
    }

    private function registerChatGptClient(): string
    {
        return (string) $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => [self::CHATGPT_REDIRECT],
        ])->assertOk()->json('client_id');
    }

    private function authorizeUrl(string $clientId): string
    {
        return '/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::CHATGPT_REDIRECT,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state-123',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier-verifier-verifier-verifier-verifier', true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Clés RSA jetables : les clés Passport de `storage/` sont gitignorées
     * (absentes d'un worktree ou d'une CI neuve).
     */
    private function useTemporaryPassportKeys(): void
    {
        $dir = sys_get_temp_dir().'/passport-keys-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents($dir.'/oauth-private.key', $private);
        file_put_contents($dir.'/oauth-public.key', openssl_pkey_get_details($key)['key']);
        chmod($dir.'/oauth-private.key', 0600);
        chmod($dir.'/oauth-public.key', 0600);

        Passport::loadKeysFrom($dir);
    }
}
