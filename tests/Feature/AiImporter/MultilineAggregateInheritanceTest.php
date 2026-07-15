<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Services\ActionPipeline;
use Tests\TestCase;

/**
 * Régression : une action `multiline_aggregate` telle qu'écrite dans les configs
 * PrestaShop réelles (somfy.json) déclare `col`/`sheet` au niveau COLONNE et
 * `type_col` au niveau FEUILLE, PAS dans l'action (qui porte `columns: []`).
 * L'ActionPipeline doit hériter ces clés, sinon l'agrégation cible `sheet=''`,
 * `columns=[]`, `type_col='type'` → résultat vide (symptôme : colonne image vide).
 */
class MultilineAggregateInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeContext(): ExecutionContext
    {
        $config = ImporterConfig::create([
            'name' => 'somfy-like',
            'config_data' => [
                'primary_sheet' => 'B01_COMMERCE',
                'join_key' => 'REFCIALE',
                'sheets' => [
                    'B03_MEDIA' => ['relation' => 'many', 'join_col' => 'B', 'type_col' => 'MTYP'],
                ],
                'mapping' => [
                    'image' => [
                        'col' => 'N',
                        'sheet' => 'B03_MEDIA',
                        'actions' => [
                            [
                                'type' => 'multiline_aggregate',
                                'filter_type' => 'PHOTO',
                                'method' => 'concat',
                                'separator' => ',',
                                'columns' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'n/a',
            'status' => 'pending',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        // Feuille média jointe : 2 PHOTO (col N = URL) + 1 NOTICE à filtrer.
        return new ExecutionContext(
            job: $job,
            row: ['REFCIALE' => '670002'],
            sheets: [
                'B03_MEDIA' => [
                    ['MTYP' => 'PHOTO', 'N' => 'https://cdn/1.jpg'],
                    ['MTYP' => 'NOTICE', 'N' => 'https://cdn/notice.pdf'],
                    ['MTYP' => 'PHOTO', 'N' => 'https://cdn/2.jpg'],
                ],
            ],
            rowNumber: 2,
        );
    }

    public function test_multiline_aggregate_inherits_sheet_col_and_type_col(): void
    {
        $ctx = $this->makeContext();
        $mapping = $ctx->job->config->config_data->getArrayCopy()['mapping']['image'];

        $result = app(ActionPipeline::class)->run(null, $mapping, $ctx);

        // Seules les lignes PHOTO sont agrégées (NOTICE filtrée par type_col=MTYP),
        // colonne N extraite, séparateur ','.
        $this->assertSame('https://cdn/1.jpg,https://cdn/2.jpg', $result);
    }

    public function test_explicit_action_keys_are_not_overridden(): void
    {
        $ctx = $this->makeContext();

        // Action qui restate explicitement columns → l'héritage ne doit PAS écraser.
        $mapping = [
            'col' => 'N',
            'sheet' => 'B03_MEDIA',
            'actions' => [[
                'type' => 'multiline_aggregate',
                'filter_type' => 'PHOTO',
                'method' => 'count',
                'columns' => ['N'],
            ]],
        ];

        $this->assertSame(2, app(ActionPipeline::class)->run(null, $mapping, $ctx));
    }
}
