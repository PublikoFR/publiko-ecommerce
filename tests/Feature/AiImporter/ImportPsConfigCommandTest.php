<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Pko\AiImporter\Models\ImporterConfig;
use Tests\TestCase;

/**
 * Non-régression : importer les VRAIES configs JSON du module PrestaShop
 * « Publiko AI Importer » (copiées en fixtures, le module PS étant read-only).
 *
 * L'import (`ai-importer:import-ps-config`) est une simple insertion DB : aucun
 * parse, aucun appel API LLM facturable — même sur `somfy.json` qui contient
 * une action `llm_transform`. On verrouille malgré tout `Http::fake()` +
 * `assertNothingSent()` pour garantir qu'aucune requête réseau ne part.
 */
class ImportPsConfigCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return __DIR__.'/../../Fixtures/ai-importer/'.$name;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Filet de sécurité coûts : toute requête sortante échouerait le test.
        Http::preventStrayRequests();
        Http::fake();
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function realPsConfigs(): array
    {
        return [
            // fichier => nombre de colonnes mappées attendu
            'somfy (multi-feuilles + category_map + llm_transform)' => ['somfy.json', 66],
            'example-avec-actions (prefix, concat, template, conditional)' => ['example-avec-actions.json', 14],
            'test-new-actions (alias uppercase/lowercase/capitalize)' => ['test-new-actions.json', 9],
        ];
    }

    /**
     * @dataProvider realPsConfigs
     */
    public function test_imports_real_ps_config_without_api_call(string $file, int $expectedColumns): void
    {
        $this->artisan('ai-importer:import-ps-config', [
            'file' => $this->fixture($file),
            '--name' => 'ps-'.pathinfo($file, PATHINFO_FILENAME),
        ])->assertExitCode(0);

        $config = ImporterConfig::query()->where('name', 'ps-'.pathinfo($file, PATHINFO_FILENAME))->first();

        $this->assertNotNull($config, "La config « {$file} » doit être insérée en base.");
        $this->assertCount(
            $expectedColumns,
            (array) ($config->config_data['mapping'] ?? []),
            "Le mapping de « {$file} » doit conserver toutes ses colonnes.",
        );

        // Garantie dure : import = zéro appel réseau (donc zéro coût API).
        Http::assertNothingSent();
    }

    public function test_singular_action_is_lifted_to_actions_array_on_import(): void
    {
        // test-new-actions.json utilise la forme PS `action` (singulier) ;
        // l'import doit la normaliser en `actions[]` consommable par le pipeline.
        $this->artisan('ai-importer:import-ps-config', [
            'file' => $this->fixture('test-new-actions.json'),
            '--name' => 'ps-normalise',
        ])->assertExitCode(0);

        $config = ImporterConfig::query()->where('name', 'ps-normalise')->firstOrFail();
        $mapping = (array) $config->config_data['mapping'];

        $upper = (array) $mapping['Test Uppercase'];
        $this->assertArrayNotHasKey('action', $upper);
        $this->assertSame('uppercase', $upper['actions'][0]['type']);

        Http::assertNothingSent();
    }
}
