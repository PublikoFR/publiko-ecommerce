<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Support\ConfigDiagnostics;
use Pko\AiImporter\Support\FilePreflight;

class FilePreflightTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $workbook = new Spreadsheet;
        $commerce = $workbook->getActiveSheet()->setTitle('B01_COMMERCE');
        $commerce->fromArray([
            ['MARQUE', 'REFCIALE', 'LIBELLE40'],
            ['SOMFY', '1001', 'Moteur'],
            ['SOMFY', '1002', 'Télécommande'],
        ]);
        $media = $workbook->createSheet()->setTitle('B03_MEDIA');
        $media->fromArray([
            ['MARQUE', 'REFCIALE', 'MTYP', 'MURL'],
            ['SOMFY', '1001', 'PHOTO', 'https://cdn.test/a.png'],
        ]);

        $this->path = tempnam(sys_get_temp_dir(), 'pko-preflight-').'.xlsx';
        (new Xlsx($workbook))->save($this->path);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'primary_sheet' => 'B01_COMMERCE',
            'join_key' => 'REFCIALE',
            'sheets' => ['B03_MEDIA' => ['relation' => 'many', 'join_col' => 'B', 'type_col' => 'MTYP']],
            'mapping' => [
                'reference' => ['col' => 'REFCIALE'],
                'name' => ['col' => 'C'],
                'image' => ['col' => 'D', 'sheet' => 'B03_MEDIA'],
            ],
        ];
    }

    public function test_matching_file_reports_row_counts_and_no_error(): void
    {
        $result = FilePreflight::check($this->path, $this->config());

        $this->assertSame(['B01_COMMERCE' => 2, 'B03_MEDIA' => 1], $result['sheets']);
        $this->assertFalse(ConfigDiagnostics::hasErrors($result['findings']));
    }

    public function test_missing_sheet_is_reported(): void
    {
        $config = $this->config();
        $config['sheets']['B02_LOGISTIQUE'] = ['relation' => 'one', 'join_col' => 'B'];

        $result = FilePreflight::check($this->path, $config);

        $this->assertTrue(ConfigDiagnostics::hasErrors($result['findings']));
        $this->assertStringContainsString('B02_LOGISTIQUE', $result['findings'][0]['message']);
    }

    public function test_missing_named_column_is_reported_with_a_case_hint(): void
    {
        $config = $this->config();
        $config['mapping']['ean'] = ['col' => 'GTIN'];
        $config['mapping']['brand_name'] = ['col' => 'marque'];

        $messages = implode(' ', array_column(FilePreflight::check($this->path, $config)['findings'], 'message'));

        $this->assertStringContainsString('GTIN', $messages);
        $this->assertStringContainsString('trouvé « MARQUE »', $messages);
    }

    public function test_unreadable_file_is_an_error_not_an_exception(): void
    {
        $result = FilePreflight::check('/nonexistent/file.xlsx', $this->config());

        $this->assertTrue(ConfigDiagnostics::hasErrors($result['findings']));
    }
}
