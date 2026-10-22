<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pko\AiCore\Enums\LlmProviderName;
use Pko\AiCore\Models\LlmConfig;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Actions\Types\LlmTransformAction;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Tests\TestCase;

/**
 * Action `llm_transform` contre un provider Claude simulé (`Http::fake`) :
 * aucun appel réel, `preventStrayRequests()` en garde-fou.
 */
class LlmTransformActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        LlmTransformAction::flushConfigCache();
    }

    private function ctx(array $row, array $ai = []): ExecutionContext
    {
        $config = ImporterConfig::create([
            'name' => 'cfg-'.uniqid(),
            'config_data' => ['mapping' => []] + ($ai !== [] ? ['ai' => $ai] : []),
        ]);
        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'x.xlsx',
        ]);

        return new ExecutionContext(job: $job->fresh('config'), row: $row, rowNumber: 2);
    }

    private function claudeConfig(bool $default = true, bool $active = true): LlmConfig
    {
        return LlmConfig::create([
            'name' => 'claude-'.uniqid(),
            'provider' => LlmProviderName::Claude,
            'api_key' => 'sk-ant-test',
            'model' => 'claude-opus-5-5',
            'is_default' => $default,
            'active' => $active,
        ]);
    }

    public function test_is_a_noop_without_active_llm_config(): void
    {
        Http::fake();
        $this->claudeConfig(default: true, active: false);

        $action = Action::make(['type' => 'llm_transform', 'prompt' => 'p', 'input_columns' => ['A']]);

        $this->assertSame('source', $action->execute('source', $this->ctx(['A' => 'x'])));
        Http::assertNothingSent();
    }

    public function test_sends_inputs_and_cached_global_context_and_reuses_config_across_rows(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Description générée']],
                'stop_reason' => 'end_turn',
            ]),
        ]);
        $this->claudeConfig();

        $action = Action::make([
            'type' => 'llm_transform',
            'prompt' => 'Rédige une description.',
            // Format PrestaShop `{col,label}` + chaîne plate.
            'input_columns' => [['col' => 'LIBELLE', 'label' => 'Libellé'], 'MARQUE'],
        ]);
        $ctx = $this->ctx(
            ['LIBELLE' => 'Moteur', 'MARQUE' => 'Somfy', 'AUTRE' => 'ignoré'],
            ['global_context' => 'Catalogue B2B.', 'context_cache' => true],
        );

        DB::enableQueryLog();
        $this->assertSame('Description générée', $action->execute('', $ctx));
        $this->assertSame('Description générée', $action->execute('', $ctx));
        $llmQueries = array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains($q['query'], 'pko_llm_configs'));
        DB::disableQueryLog();

        $this->assertCount(1, $llmQueries, 'La LlmConfig doit être résolue une seule fois par action, pas par ligne.');
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $user = (string) ($body['messages'][0]['content'] ?? '');

            return str_starts_with($user, 'Rédige une description.')
                && str_contains($user, '"LIBELLE": "Moteur"')
                && str_contains($user, '"MARQUE": "Somfy"')
                && ! str_contains($user, 'AUTRE')
                && ($body['system'][0]['text'] ?? null) === 'Catalogue B2B.'
                && ($body['system'][0]['cache_control']['type'] ?? null) === 'ephemeral';
        });
    }

    public function test_input_columns_read_mapped_outputs_before_raw_row(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
            ]),
        ]);
        $this->claudeConfig();

        // Clés de sortie du mapping (format PS) : `name` est déjà calculé (préfixé),
        // `features` est un hash structuré, `ean13` n'est pas mappé → colonne brute.
        $action = Action::make([
            'type' => 'llm_transform',
            'prompt' => 'p',
            'input_columns' => ['name', 'features', 'ean13'],
        ]);
        $ctx = $this->ctx(['A' => 'moteur', 'ean13' => '3660849542641']);
        $ctx->setOutput('name', 'MOTEUR SOMFY');
        $ctx->setOutput('features', ['marque' => ['somfy']]);

        $action->execute('', $ctx);

        Http::assertSent(function ($request): bool {
            $user = (string) ($request->data()['messages'][0]['content'] ?? '');

            return str_contains($user, '"name": "MOTEUR SOMFY"')
                && str_contains($user, '"features": "{\\"marque\\":[\\"somfy\\"]}"')
                && str_contains($user, '"ean13": "3660849542641"');
        });
    }

    public function test_json_output_strips_code_fences_and_extracts_key(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => "```json\n{\"description\": \"Texte\", \"autre\": 1}\n```"]],
                'stop_reason' => 'end_turn',
            ]),
        ]);
        $this->claudeConfig();

        $withKey = Action::make(['type' => 'llm_transform', 'prompt' => 'p', 'input_columns' => ['A'],
            'output_format' => 'json', 'output_json_key' => 'description']);
        $this->assertSame('Texte', $withKey->execute('', $this->ctx(['A' => 'x'])));

        $whole = Action::make(['type' => 'llm_transform', 'prompt' => 'p', 'input_columns' => ['A'],
            'output_format' => 'json']);
        $this->assertSame(['description' => 'Texte', 'autre' => 1], $whole->execute('', $this->ctx(['A' => 'x'])));
    }

    public function test_explicit_llm_config_id_as_string_is_honoured(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
            ]),
        ]);
        $this->claudeConfig(default: true, active: false);
        $explicit = $this->claudeConfig(default: false, active: true);

        $action = Action::make([
            'type' => 'llm_transform',
            'llm_config_id' => (string) $explicit->id, // format PS : identifiant en chaîne
            'prompt' => 'p',
            'input_columns' => ['A'],
        ]);

        $this->assertSame('ok', $action->execute('', $this->ctx(['A' => 'x'])));
        Http::assertSentCount(1);
    }
}
