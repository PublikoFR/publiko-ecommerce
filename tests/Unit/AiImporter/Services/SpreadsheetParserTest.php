<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Services;

use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Services\SpreadsheetParser;

/**
 * Parité load() / loadStreamed() et lecture des cellules (formules, texte riche).
 */
class SpreadsheetParserTest extends TestCase
{
    private string $path;

    /** @var array<string, mixed> */
    private array $config = [
        'primary_sheet' => 'Produits',
        'join_key' => 'REF',
        'sheets' => [
            'Produits' => ['skip_first_row' => true],
            'Logistique' => ['skip_first_row' => true, 'join_key' => 'REF', 'relation' => 'one'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $book = new Spreadsheet;
        $primary = $book->getActiveSheet()->setTitle('Produits');
        $primary->fromArray(['REF', 'NOM', 'PRIX_HT', 'PRIX_TTC'], null, 'A1');
        for ($i = 1; $i <= 250; $i++) {
            $primary->fromArray(["R{$i}", "Produit {$i}", 10 * $i], null, 'A'.($i + 1));
            $primary->setCellValue('D'.($i + 1), '=C'.($i + 1).'*1.2');
        }
        // Ligne vide au milieu, conservée dans les deux modes.
        $primary->fromArray([null, null, null, null], null, 'A252');
        $primary->fromArray(['R-LAST', 'Dernier', 5], null, 'A253');

        $rich = new RichText;
        $rich->createText('Texte ');
        $rich->createTextRun('gras')->getFont()?->setBold(true);
        $primary->setCellValue('B2', $rich);

        $logistics = $book->createSheet()->setTitle('Logistique');
        $logistics->fromArray(['REF', 'POIDS'], null, 'A1');
        $logistics->fromArray(['R1', 1.5], null, 'A2');
        $logistics->fromArray(['R200', 2.5], null, 'A3');

        $this->path = tempnam(sys_get_temp_dir(), 'parser').'.xlsx';
        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, count: int, joins: array<string, mixed>}
     */
    private function read(bool $streamed): array
    {
        $parser = new SpreadsheetParser;
        $streamed
            ? $parser->loadStreamed($this->path, $this->config, 100)
            : $parser->load($this->path, $this->config);

        $rows = iterator_to_array($parser->iterateRows('Produits'));

        return [
            'rows' => $rows,
            'count' => $parser->countRows('Produits'),
            'joins' => [
                'R1' => $parser->secondarySheetsFor('R1'),
                'R200' => $parser->secondarySheetsFor('R200'),
                'R2' => $parser->secondarySheetsFor('R2'),
            ],
        ];
    }

    public function test_streamed_mode_matches_full_load(): void
    {
        $full = $this->read(false);
        $streamed = $this->read(true);

        $this->assertSame(252, $full['count']);
        $this->assertSame($full['count'], $streamed['count']);
        $this->assertCount(252, $full['rows']);
        $this->assertEquals($full['rows'], $streamed['rows']);
        $this->assertEquals($full['joins'], $streamed['joins']);
    }

    public function test_secondary_sheet_is_fully_indexed_in_streamed_mode(): void
    {
        $joins = $this->read(true)['joins'];

        $this->assertSame(1.5, $joins['R1']['Logistique'][0]['POIDS']);
        $this->assertSame(2.5, $joins['R200']['Logistique'][0]['POIDS']);
        $this->assertSame([], $joins['R2']['Logistique']);
    }

    public function test_formula_yields_calculated_value(): void
    {
        foreach ([false, true] as $streamed) {
            $rows = $this->read($streamed)['rows'];

            $this->assertEqualsWithDelta(12.0, $rows[2]['PRIX_TTC'], 0.0001);
            $this->assertEqualsWithDelta(3000.0, $rows[251]['PRIX_TTC'], 0.0001);
        }
    }

    public function test_rich_text_yields_plain_string(): void
    {
        foreach ([false, true] as $streamed) {
            $rows = $this->read($streamed)['rows'];

            $this->assertSame('Texte gras', $rows[2]['NOM']);
            $this->assertSame('{"NOM":"Texte gras"}', json_encode(['NOM' => $rows[2]['NOM']]));
        }
    }
}
