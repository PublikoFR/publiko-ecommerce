<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Support\PipelineEditorManifest;

/**
 * Garde-fou de l'éditeur de pipeline custom (modal « Configurer »).
 *
 * Deux invariants critiques :
 *   1. Chaque type d'action de la palette est exécutable côté Lunar — sans quoi
 *      une action ajoutée dans l'éditeur planterait à l'import.
 *   2. Le `config_data` survit au round-trip hydrate → dehydrate du Resource :
 *      l'éditeur lit/écrit la forme CANONIQUE des actions, qui doit traverser le
 *      pont Filament sans déformation (cf. revue : config → éditeur → config).
 */
class PipelineEditorTest extends TestCase
{
    public function test_every_palette_action_type_is_executable(): void
    {
        foreach (PipelineEditorManifest::actionTypes() as $def) {
            $type = $def['type'];
            // resolve() lève si le type (y compris alias legacy multiply/uppercase…)
            // n'est pas enregistré : c'est la garantie de round-trip vers l'exécuteur.
            $class = ActionRegistry::resolve($type);
            $this->assertNotEmpty($class, "Type d'action non exécutable : {$type}");
        }
    }

    public function test_every_palette_action_references_a_known_group(): void
    {
        $groups = PipelineEditorManifest::groups();
        foreach (PipelineEditorManifest::actionTypes() as $def) {
            $this->assertArrayHasKey(
                $def['group'],
                $groups,
                "Groupe inconnu pour {$def['type']} : {$def['group']}"
            );
        }
    }

    public function test_condition_action_is_present_in_logic_group(): void
    {
        $logic = array_filter(
            PipelineEditorManifest::actionTypes(),
            static fn (array $d): bool => $d['group'] === 'logic'
        );
        $types = array_column($logic, 'type');

        $this->assertContains('condition', $types);
    }

    public function test_canonical_actions_survive_hydrate_dehydrate_round_trip(): void
    {
        // Pipeline représentatif du screenshot « Prix HT » : condition (SI … ALORS
        // multiply) avec SINON multiply, puis round (PUIS, à plat après la condition).
        $mapping = [
            'price' => [
                'sheet' => 'B01_COMMERCE',
                'col' => 'M',
                'default' => '0',
                'actions' => [
                    [
                        'type' => 'condition',
                        'branches' => [
                            [
                                'logic' => 'AND',
                                'rules' => [
                                    ['field' => 'B01_COMMERCE:AB', 'operator' => '=', 'value' => 'GTK'],
                                ],
                                'actions' => [
                                    ['type' => 'multiply', 'value' => 1.2],
                                ],
                            ],
                        ],
                        'else_actions' => [
                            ['type' => 'multiply', 'value' => 1.2],
                        ],
                    ],
                    ['type' => 'round', 'decimals' => 2],
                ],
            ],
        ];

        $config = ['type' => 'XLSX', 'mapping' => $mapping];

        $hydrated = ImporterConfigResource::hydrateVisual(['config_data' => $config]);
        $back = ImporterConfigResource::dehydrateVisual(['config_data' => $hydrated['config_data']]);

        $this->assertEquals($mapping, $back['config_data']['mapping']);
        // Les clés scratch de l'éditeur visuel ne fuient jamais dans le canonique.
        $this->assertArrayNotHasKey('mapping_repeater', $back['config_data']);
        $this->assertArrayNotHasKey('sheets_repeater', $back['config_data']);
    }

    public function test_empty_mapping_round_trips_to_no_mapping(): void
    {
        $hydrated = ImporterConfigResource::hydrateVisual(['config_data' => ['type' => 'CSV']]);
        $back = ImporterConfigResource::dehydrateVisual(['config_data' => $hydrated['config_data']]);

        $this->assertArrayNotHasKey('mapping', $back['config_data']);
        $this->assertSame('CSV', $back['config_data']['type']);
    }
}
