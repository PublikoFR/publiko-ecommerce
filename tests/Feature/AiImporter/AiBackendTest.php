<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pko\AiCore\Enums\LlmProviderName;
use Pko\AiCore\Llm\LlmManager;
use Pko\AiCore\Llm\LlmUnavailableException;
use Pko\AiCore\Llm\ModelAliasResolver;
use Pko\AiCore\Models\LlmConfig;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Actions\Types\LlmTransformAction;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Support\AiPreflight;
use Pko\AiImporter\Support\LlmActionNormalizer;
use Tests\TestCase;

/**
 * Désignation portable des modèles IA : fournisseur + alias (claude / sonnet)
 * au lieu d'un id de configuration propre à une base. Aucun appel réel.
 */
class AiBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        LlmTransformAction::flushConfigCache();
    }

    private function claude(string $model = 'claude-sonnet-5-5', bool $default = true): LlmConfig
    {
        return LlmConfig::create([
            'name' => 'claude-'.uniqid(),
            'provider' => LlmProviderName::Claude,
            'api_key' => 'sk-ant-test',
            'model' => $model,
            'is_default' => $default,
            'active' => true,
        ]);
    }

    private function fakeAnthropic(): void
    {
        Http::fake([
            'api.anthropic.com/v1/models*' => Http::response(['data' => [
                ['id' => 'claude-opus-5-5', 'created_at' => '2026-09-01T00:00:00Z'],
                ['id' => 'claude-sonnet-5', 'created_at' => '2026-03-01T00:00:00Z'],
                ['id' => 'claude-sonnet-6', 'created_at' => '2026-10-01T00:00:00Z'],
            ]]),
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
            ]),
        ]);
    }

    public function test_alias_resolves_to_newest_model_of_the_family(): void
    {
        $this->fakeAnthropic();
        $resolver = app(ModelAliasResolver::class);

        $this->assertSame('claude-sonnet-6', $resolver->resolve(LlmProviderName::Claude, 'Sonnet', 'k'));
        $this->assertSame('claude-sonnet-5-5', $resolver->resolve(LlmProviderName::Claude, 'claude-sonnet-5-5', 'k'), 'identifiant exact inchangé');
    }

    public function test_alias_falls_back_to_config_table_when_models_api_fails(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('down', 500)]);

        $this->assertSame('claude-haiku-5-5', app(ModelAliasResolver::class)->resolve(LlmProviderName::Claude, 'haiku', 'k'));
    }

    public function test_backend_without_active_key_is_reported(): void
    {
        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('Aucune configuration IA active pour le fournisseur « openai »');

        app(LlmManager::class)->resolveBackend('openai', 'gpt-5');
    }

    public function test_llm_transform_with_backend_sends_resolved_model_and_logs_it(): void
    {
        $this->fakeAnthropic();
        $this->claude();
        $job = ImportJob::create(['config_id' => ImporterConfig::create(['name' => 'c', 'config_data' => ['mapping' => []]])->id, 'input_file_path' => 'x']);

        $action = Action::make(['type' => 'llm_transform', 'prompt' => 'p', 'backend' => 'claude', 'model' => 'sonnet']);
        $this->assertSame('ok', $action->execute('', new ExecutionContext(job: $job->fresh('config'), rowNumber: 2)));

        Http::assertSent(fn ($r): bool => str_contains($r->url(), '/v1/messages') && $r->data()['model'] === 'claude-sonnet-6');
        $this->assertTrue(ImportLog::where('import_job_id', $job->id)->where('message', 'like', 'Modèle IA : claude / sonnet → claude-sonnet-6')->exists());
    }

    public function test_normalizer_converts_config_id_to_backend_and_family_alias(): void
    {
        $config = $this->claude('claude-sonnet-5-5');

        $out = LlmActionNormalizer::config(['mapping' => [
            'description' => ['actions' => [['type' => 'llm_transform', 'llm_config_id' => (string) $config->id, 'prompt' => 'p']]],
            'features' => ['actions' => [['type' => 'condition', 'branches' => [['actions' => [['type' => 'llm_transform', 'llm_config_id' => 999]]]]]]],
        ]]);

        $this->assertSame(['type' => 'llm_transform', 'prompt' => 'p', 'backend' => 'claude', 'model' => 'sonnet'], $out['mapping']['description']['actions'][0]);
        // Id inconnu : retiré, l'action suivra la config par défaut.
        $this->assertSame(['type' => 'llm_transform'], $out['mapping']['features']['actions'][0]['branches'][0]['actions'][0]);
    }

    public function test_preflight_blocks_unusable_backend_and_passes_working_one(): void
    {
        $this->fakeAnthropic();
        $data = ['mapping' => [
            'description' => ['actions' => [['type' => 'llm_transform', 'backend' => 'claude', 'model' => 'sonnet']]],
            'category' => ['actions' => [['type' => 'llm_transform', 'backend' => 'openai', 'model' => 'gpt-5']]],
            'name' => ['actions' => [['type' => 'trim']]],
        ]];

        $this->assertCount(2, AiPreflight::check($data), 'aucune clé : les deux fournisseurs sont bloqués');

        $this->claude();
        $errors = AiPreflight::check($data);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Colonne(s) category (openai / gpt-5)', $errors[0]);

        $this->assertSame([], AiPreflight::check($data, ['description', 'name']), 'colonnes non calculées ignorées');
    }

    public function test_preflight_reports_model_rejected_by_the_api(): void
    {
        Http::fake(['api.anthropic.com/v1/messages' => Http::response(['error' => ['message' => 'model: not_found']], 404)]);
        $this->claude();

        $errors = AiPreflight::check(['mapping' => ['d' => ['actions' => [['type' => 'llm_transform', 'backend' => 'claude', 'model' => 'claude-inexistant-1']]]]]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Claude API error (404)', $errors[0]);
    }
}
