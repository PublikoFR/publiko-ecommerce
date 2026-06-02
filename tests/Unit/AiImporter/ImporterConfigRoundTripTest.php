<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;

/**
 * Pure round-trip guard for the visual editor serialisation. Asserts that
 * canonical `config_data` survives `hydrateVisual()` → `dehydrateVisual()`
 * unchanged, including every action-type family and the `condition` branching
 * shape. No DB, no Filament rendering.
 */
class ImporterConfigRoundTripTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function roundTrip(array $config): array
    {
        $hydrated = ImporterConfigResource::hydrateVisual(['config_data' => $config]);
        $dehydrated = ImporterConfigResource::dehydrateVisual($hydrated);

        return ImporterConfigResource::arrayify($dehydrated['config_data']);
    }

    public function test_sheets_and_primary_keys_round_trip(): void
    {
        $config = [
            'primary_sheet' => 'B01_COMMERCE',
            'join_key' => 'REFCIALE',
            'sheets' => [
                'B02_LOGISTIQUE' => ['relation' => 'one', 'join_key' => 'REF', 'type' => 'logistics', 'skip_first_row' => true],
                'B03_IMAGES' => ['relation' => 'many', 'type' => 'images'],
            ],
        ];

        $this->assertSame($config, $this->roundTrip($config));
    }

    public function test_ai_block_round_trips_and_is_dropped_when_empty(): void
    {
        $with = ['ai' => ['context_cache' => true, 'global_context' => 'Ton de marque B2B.']];
        $this->assertSame($with, $this->roundTrip($with));

        $emptyAi = ['ai' => ['context_cache' => false, 'global_context' => '']];
        $this->assertArrayNotHasKey('ai', $this->roundTrip($emptyAi));
    }

    public function test_simple_action_pipeline_round_trips_with_typed_params(): void
    {
        $config = [
            'mapping' => [
                'price_cents' => [
                    'col' => 'M',
                    'sheet' => 'B01_COMMERCE',
                    'default' => '0',
                    'actions' => [
                        ['type' => 'math', 'operation' => 'multiply', 'value' => 1.2],
                        ['type' => 'round', 'decimals' => 2],
                    ],
                ],
                'name' => [
                    'col' => 'B',
                    'actions' => [
                        ['type' => 'trim'],
                        ['type' => 'change_case', 'mode' => 'capitalize'],
                    ],
                ],
            ],
        ];

        $this->assertSame($config, $this->roundTrip($config));
    }

    public function test_map_action_with_json_params_round_trips(): void
    {
        $config = [
            'mapping' => [
                'tax_class_handle' => [
                    'col' => 'TVA',
                    'actions' => [
                        [
                            'type' => 'map',
                            'values' => ['20' => 'standard', '5.5' => 'reduced'],
                            'default' => 'standard',
                            'multi_value' => false,
                            'separator' => ',',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($config, $this->roundTrip($config));
    }

    public function test_source_type_round_trips(): void
    {
        $config = [
            'type' => 'FAB-DIS',
            'primary_sheet' => 'B01_COMMERCE',
        ];

        $this->assertSame($config, $this->roundTrip($config));
    }

    public function test_full_llm_transform_action_round_trips(): void
    {
        $config = [
            'mapping' => [
                'description' => [
                    'col' => 'DESC',
                    'actions' => [
                        [
                            'type' => 'llm_transform',
                            'llm_config_id' => 3,
                            'prompt' => 'Reformule la description produit.',
                            'input_columns' => ['DESC', 'NOM'],
                            'output_format' => 'json',
                            'output_json_key' => 'description',
                            'additional_context' => 'Ton B2B, vouvoiement.',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($config, $this->roundTrip($config));
    }

    public function test_multi_value_map_action_round_trips(): void
    {
        $config = [
            'mapping' => [
                'features' => [
                    'col' => 'TAGS',
                    'actions' => [
                        [
                            'type' => 'map',
                            'values' => ['A' => 'Alpha', 'B' => 'Beta'],
                            'default' => null,
                            'multi_value' => true,
                            'separator' => '|',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($config, $this->roundTrip($config));
    }

    public function test_condition_branching_round_trips(): void
    {
        $config = [
            'mapping' => [
                'price_cents' => [
                    'col' => 'M',
                    'actions' => [
                        [
                            'type' => 'condition',
                            'branches' => [
                                [
                                    'logic' => 'AND',
                                    'rules' => [
                                        ['field' => 'B01_COMMERCE:AB', 'operator' => '=', 'value' => 'GTK'],
                                        ['field' => 'col_value', 'operator' => '>', 'value' => '0'],
                                    ],
                                    'actions' => [
                                        ['type' => 'math', 'operation' => 'multiply', 'value' => 1.2],
                                    ],
                                ],
                            ],
                            'else_actions' => [
                                ['type' => 'math', 'operation' => 'multiply', 'value' => 1.05],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($config, $this->roundTrip($config));
    }

    public function test_all_registered_action_types_remain_editable(): void
    {
        $samples = [
            ['type' => 'math', 'operation' => 'add', 'value' => 5.5],
            ['type' => 'round', 'decimals' => 0],
            ['type' => 'change_case', 'mode' => 'upper'],
            ['type' => 'trim'],
            ['type' => 'truncate', 'length' => 120, 'suffix' => '…'],
            ['type' => 'slugify'],
            ['type' => 'prefix', 'text' => 'REF-', 'separator' => ''],
            ['type' => 'suffix', 'text' => '-FR', 'separator' => ''],
            ['type' => 'replace', 'search' => 'a', 'replace' => 'b'],
            ['type' => 'regex_replace', 'pattern' => '/\\s+/', 'replace' => ' '],
            ['type' => 'date_format', 'from' => 'Y-m-d', 'to' => 'd/m/Y'],
            ['type' => 'validate_ean13'],
            ['type' => 'concat', 'sources' => ['A', 'B'], 'separator' => ' '],
            ['type' => 'template', 'template' => '{A}-{B}', 'sources' => ['A', 'B']],
            ['type' => 'copy', 'col' => 'Z'],
            ['type' => 'llm_transform', 'prompt' => 'Reformule', 'input_columns' => ['DESC'], 'output_format' => 'string'],
            ['type' => 'multiline_aggregate', 'sheet' => 'B03', 'method' => 'concat', 'separator' => '|', 'columns' => ['URL']],
            ['type' => 'parse_features_string', 'family_separator' => '|', 'kv_separator' => ':', 'value_separator' => ',', 'slugify' => true],
            ['type' => 'parse_category_breadcrumb', 'path_separator' => ',', 'segment_separator' => '>', 'mode' => 'leaf', 'slugify' => true, 'output_separator' => ','],
        ];

        $config = ['mapping' => ['name' => ['col' => 'A', 'actions' => $samples]]];

        $this->assertSame($config, $this->roundTrip($config));
    }
}
