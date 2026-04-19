<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Pko\AiCore\Enums\LlmProviderName;
use Pko\AiCore\Llm\LlmManager;
use Pko\AiCore\Models\LlmConfig;
use Tests\TestCase;

class LlmProvidersTest extends TestCase
{
    use RefreshDatabase;

    public function test_claude_provider_returns_text_from_messages_api(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Réponse Claude'],
                ],
            ], 200),
        ]);

        $config = LlmConfig::create([
            'name' => 'claude-test',
            'provider' => LlmProviderName::Claude,
            'api_key' => 'sk-ant-test',
            'model' => 'claude-sonnet-4-6',
            'active' => true,
        ]);

        $result = (new LlmManager)->forConfig($config)->transform('Salut', ['col' => 'valeur']);

        $this->assertSame('Réponse Claude', $result);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.anthropic.com')
                && $request->hasHeader('x-api-key', 'sk-ant-test')
                && $request->hasHeader('anthropic-version');
        });
    }

    public function test_openai_provider_returns_text_from_chat_completions(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Réponse OpenAI']],
                ],
            ], 200),
        ]);

        $config = LlmConfig::create([
            'name' => 'openai-test',
            'provider' => LlmProviderName::OpenAi,
            'api_key' => 'sk-test',
            'model' => 'gpt-4o',
            'active' => true,
        ]);

        $result = (new LlmManager)->forConfig($config)->transform('Hello');

        $this->assertSame('Réponse OpenAI', $result);
        Http::assertSent(fn ($req): bool => str_contains($req->url(), 'api.openai.com')
            && $req->hasHeader('Authorization', 'Bearer sk-test'));
    }

    public function test_claude_throws_on_error_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'bad model'], 400),
        ]);

        $config = LlmConfig::create([
            'name' => 'claude-err',
            'provider' => LlmProviderName::Claude,
            'api_key' => 'sk-ant-test',
            'model' => 'nope',
            'active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Claude API error/');

        (new LlmManager)->forConfig($config)->transform('x');
    }

    public function test_api_key_is_encrypted_at_rest(): void
    {
        $config = LlmConfig::create([
            'name' => 'enc-test',
            'provider' => LlmProviderName::Claude,
            'api_key' => 'my-secret-key',
            'model' => 'claude-sonnet-4-6',
        ]);

        // Read the RAW column value (not via the cast).
        $raw = (string) \DB::table('pko_llm_configs')->where('id', $config->id)->value('api_key');
        $this->assertNotSame('my-secret-key', $raw);
        $this->assertStringStartsWith('eyJ', $raw); // Laravel encrypted payloads start with eyJ (base64 JSON)

        // But reading via the model decrypts it:
        $this->assertSame('my-secret-key', $config->fresh()->api_key);
    }
}
