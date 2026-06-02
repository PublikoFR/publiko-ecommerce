<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Services\ActionPipeline;
use Pko\AiImporter\Services\SpreadsheetParser;
use Tests\TestCase;

/**
 * Parse e2e couvrant les actions PrestaShop critiques sur un classeur
 * multi-feuilles (B01_COMMERCE primaire + B02_LOGISTIQUE/B03_MEDIA jointes
 * sur `REFCIALE`). La config reproduit fidèlement les formes PS réelles
 * (prefix colonne, multiply+comment, category_map, conditional, concat/template
 * multi-feuilles, alias change_case) SANS aucune action `llm_transform`
 * facturable côté API.
 *
 * ⚠️ Sécurité coûts : `Http::fake()` + `assertNothingSent()` + AUCUN
 * `LlmConfig` actif → la colonne `llm_transform` est un no-op, et toute requête
 * réseau échouerait le test. Cf. la consigne projet (CLAUDE.md / brain²).
 */
class PsConfigParseE2eTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_parses_multi_sheet_ps_actions_into_staging_without_api_call(): void
    {
        Storage::fake('local');
        $path = 'ai-importer/inputs/ps-fixture.xlsx';
        Storage::disk('local')->put($path, $this->buildWorkbook());

        $config = ImporterConfig::create([
            'name' => 'ps-multi-sheet',
            'config_data' => $this->psLikeConfig(),
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => $path,
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        (new ParseFileToStagingJob($job->id))->handle(
            app(SpreadsheetParser::class),
            app(ActionPipeline::class),
        );

        $job->refresh();

        $this->assertSame(JobStatus::Parsed, $job->status, $job->error_message ?? '');
        $this->assertSame(2, $job->stagingRecords()->count());

        $rows = $job->stagingRecords()->orderBy('row_number')->get()
            ->mapWithKeys(fn ($r) => [$r->data['reference'] => (array) $r->data]);

        // --- Ligne SOM100 (en stock, catégorie connue) ---
        $a = $rows['FAASOM100'];

        // prefix colonne legacy : REFCIALE « SOM100 » → « FAASOM100 ».
        $this->assertSame('FAASOM100', $a['reference']);
        // alias change_case réellement enregistrés.
        $this->assertSame('MOTEUR SOMFY', $a['name']);          // uppercase
        $this->assertSame('moteur somfy', $a['url_key']);       // lowercase
        $this->assertSame('Moteur Somfy', $a['brand_name']);    // capitalize
        // multiply + comment toléré : 100 × 1.2 = 120.
        $this->assertEqualsWithDelta(120.0, (float) $a['price_cents'], 0.001);
        // category_map → leaf slugifié.
        $this->assertSame('motorisation', $a['collections']);
        // conditional « > 0 » sur STOCK=7 (feuille B02, relation one).
        $this->assertSame('1', $a['orderable']);
        // concat multi-feuilles (B03_MEDIA, deux images jointes).
        $this->assertSame('http://img/1.jpg,http://img/2.jpg', $a['images']);
        // template multi-source (valeurs brutes lues sur la feuille primaire).
        $this->assertSame('moteur somfy - SOM100', $a['meta_title']);
        // replace (espaces supprimés de l'EAN).
        $this->assertSame('3770000000017', $a['ean']);
        // llm_transform : no-op (aucun LlmConfig actif) → valeur source inchangée.
        $this->assertSame('moteur somfy', $a['features']);

        // --- Ligne SOM200 (rupture, catégorie inconnue) ---
        $b = $rows['FAASOM200'];
        $this->assertSame('0', $b['orderable']);                // STOCK=0 → conditional faux
        $this->assertSame('divers', $b['collections']);         // default_category
        $this->assertSame('http://img/3.jpg', $b['images']);    // 2e image vide filtrée

        // Toutes les lignes parsées proprement, aucune en erreur.
        $this->assertSame(
            0,
            $job->stagingRecords()->where('status', StagingStatus::Error)->count(),
        );

        // GARANTIE DURE : aucun appel réseau (donc aucun coût API LLM).
        Http::assertNothingSent();
    }

    /**
     * Construit un classeur XLSX 3 feuilles en mémoire et renvoie ses octets.
     */
    private function buildWorkbook(): string
    {
        $book = new Spreadsheet;

        $b01 = $book->getActiveSheet();
        $b01->setTitle('B01_COMMERCE');
        $b01->fromArray([
            ['REFCIALE', 'LIBELLE', 'PRIX_NET', 'CATEG', 'CODEBARRE'],
            ['SOM100', 'moteur somfy', 100, 'Moteurs', '3 770 000 000 017'],
            ['SOM200', 'rail alu', 50, 'Accessoires', '0000000000000'],
        ]);

        $b02 = $book->createSheet();
        $b02->setTitle('B02_LOGISTIQUE');
        $b02->fromArray([
            ['REFCIALE', 'LARGEUR_MM', 'STOCK', 'POIDS'],
            ['SOM100', 850, 7, 2.345],
            ['SOM200', 200, 0, 1.0],
        ]);

        $b03 = $book->createSheet();
        $b03->setTitle('B03_MEDIA');
        $b03->fromArray([
            ['REFCIALE', 'URL_IMAGE_1', 'URL_IMAGE_2', 'MTYP'],
            ['SOM100', 'http://img/1.jpg', 'http://img/2.jpg', 'PHOTO'],
            ['SOM200', 'http://img/3.jpg', '', 'PHOTO'],
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'psfix').'.xlsx';
        (new Xlsx($book))->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        $book->disconnectWorksheets();

        return $bytes;
    }

    /**
     * Config fidèle aux formes PS, sans aucune action `llm_transform` facturable
     * (la seule colonne `llm_transform` présente reste un no-op volontaire).
     *
     * @return array<string, mixed>
     */
    private function psLikeConfig(): array
    {
        return [
            'primary_sheet' => 'B01_COMMERCE',
            'join_key' => 'REFCIALE',
            'sheets' => [
                'B01_COMMERCE' => ['relation' => 'one'],
                'B02_LOGISTIQUE' => ['relation' => 'one', 'join_col' => 'REFCIALE'],
                'B03_MEDIA' => ['relation' => 'many', 'join_col' => 'REFCIALE', 'type_col' => 'MTYP'],
            ],
            'mapping' => [
                'reference' => [
                    'col' => 'REFCIALE', 'sheet' => 'B01_COMMERCE',
                    'prefix' => 'FAA', 'comment' => 'Référence préfixée fournisseur',
                ],
                'name' => ['col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE', 'actions' => [['type' => 'uppercase']]],
                'url_key' => ['col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE', 'actions' => [['type' => 'lowercase']]],
                'brand_name' => ['col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE', 'actions' => [['type' => 'capitalize']]],
                'price_cents' => [
                    'col' => 'PRIX_NET', 'sheet' => 'B01_COMMERCE', 'default' => '0',
                    'actions' => [['type' => 'multiply', 'value' => 1.2, 'comment' => 'PA distributeur × 1.2']],
                ],
                'collections' => [
                    'col' => 'CATEG', 'sheet' => 'B01_COMMERCE',
                    'actions' => [[
                        'type' => 'category_map',
                        'values' => ['Moteurs' => 'Accueil>Électronique>Motorisation'],
                        'default_category' => 'Accueil>Divers',
                    ]],
                ],
                'orderable' => [
                    'col' => 'STOCK', 'sheet' => 'B02_LOGISTIQUE',
                    'actions' => [['type' => 'conditional', 'condition' => '> 0', 'if_true' => '1', 'if_false' => '0']],
                ],
                'images' => [
                    'actions' => [[
                        'type' => 'concat', 'separator' => ',',
                        'sources' => [
                            ['col' => 'URL_IMAGE_1', 'sheet' => 'B03_MEDIA'],
                            ['col' => 'URL_IMAGE_2', 'sheet' => 'B03_MEDIA'],
                        ],
                    ]],
                ],
                'meta_title' => [
                    'actions' => [[
                        'type' => 'template', 'template' => '{NOM} - {REF}',
                        'sources' => [
                            'NOM' => ['col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE'],
                            'REF' => ['col' => 'REFCIALE', 'sheet' => 'B01_COMMERCE'],
                        ],
                    ]],
                ],
                'ean' => [
                    'col' => 'CODEBARRE', 'sheet' => 'B01_COMMERCE',
                    'actions' => [['type' => 'replace', 'search' => ' ', 'replace' => '']],
                ],
                // Colonne llm_transform laissée volontairement : doit rester un
                // no-op (aucun LlmConfig actif) et ne JAMAIS émettre de requête.
                'features' => [
                    'col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE',
                    'actions' => [['type' => 'llm_transform', 'prompt' => 'noop', 'input_columns' => ['LIBELLE']]],
                ],
            ],
        ];
    }
}
