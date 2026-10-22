<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Pko\AiCore\Contracts\LlmProviderInterface;
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
        Sleep::fake();
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

    public function test_claude_skips_thinking_block_and_reads_first_text_block(): void
    {
        // Opus/Sonnet/Haiku 5.x : réflexion active par défaut → le premier bloc
        // est un `thinking` (texte vide). Lire `content.0.text` rendrait ''.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'thinking', 'thinking' => ''],
                    ['type' => 'text', 'text' => 'Texte utile'],
                ],
                'stop_reason' => 'end_turn',
            ], 200),
        ]);

        $result = $this->claude()->transform('Salut', ['col' => 'v']);

        $this->assertSame('Texte utile', $result);
    }

    public function test_claude_sends_effort_and_max_tokens_from_config_options(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
            ], 200),
        ]);

        $this->claude(['effort' => 'low', 'max_tokens' => '512'])
            ->transform('Prompt', ['a' => 'b'], ['system' => 'Contexte', 'cache_system' => true]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return ($body['output_config']['effort'] ?? null) === 'low'
                && ($body['max_tokens'] ?? null) === 512
                && ($body['system'][0]['cache_control']['type'] ?? null) === 'ephemeral'
                && ($body['system'][0]['text'] ?? null) === 'Contexte'
                && str_contains((string) ($body['messages'][0]['content'] ?? ''), '"a": "b"');
        });
    }

    public function test_claude_refusal_throws_instead_of_returning_empty_string(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [],
                'stop_reason' => 'refusal',
                'stop_details' => ['type' => 'refusal', 'category' => 'cyber', 'explanation' => 'nope'],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/refusé.*cyber/');

        $this->claude()->transform('x');
    }

    public function test_claude_does_not_retry_definitive_4xx(): void
    {
        Sleep::fake();
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'bad model'], 400),
        ]);

        try {
            $this->claude()->transform('x');
            $this->fail('expected exception');
        } catch (\RuntimeException) {
        }

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_claude_retries_transient_529_then_succeeds(): void
    {
        Sleep::fake();
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['error' => ['type' => 'overloaded_error']], 529)
                ->push(['error' => ['type' => 'rate_limit_error']], 429, ['retry-after' => '3'])
                ->push(['content' => [['type' => 'text', 'text' => 'enfin']], 'stop_reason' => 'end_turn'], 200),
        ]);

        $this->assertSame('enfin', $this->claude()->transform('x'));

        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
        // 2ᵉ attente : l'en-tête retry-after (3 s) prime sur le backoff configuré.
        Sleep::assertSequence([
            Sleep::usleep(2000 * 1000),
            Sleep::usleep(3000 * 1000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function claude(array $options = []): LlmProviderInterface
    {
        $config = LlmConfig::create([
            'name' => 'claude-'.uniqid(),
            'provider' => LlmProviderName::Claude,
            'api_key' => 'sk-ant-test',
            'model' => 'claude-opus-5-5',
            'options' => $options,
            'active' => true,
        ]);

        return (new LlmManager)->forConfig($config);
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
