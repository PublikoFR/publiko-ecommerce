<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Support\ProductFieldCatalog;
use Pko\CatalogFeatures\Models\BrandFamily;
use Tests\TestCase;

/**
 * Famille fabricant (famille de remise FAB-DIS) à l'import : code EXACT, propre à la marque.
 */
class WriterBrandFamilyTest extends TestCase
{
    use RefreshDatabase;

    private ImportJob $job;

    private int $row = 1;

    protected function setUp(): void
    {
        parent::setUp();
        ProductType::firstOrCreate(['name' => 'Test Type']);
        TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);

        $this->job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    private function write(array $data, array $options = []): StagingRecord
    {
        $record = StagingRecord::create([
            'import_job_id' => $this->job->id,
            'row_number' => $this->row++,
            'data' => $data,
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->configure($options)->write($record);

        return $record->refresh();
    }

    private function product(string $sku): Product
    {
        return ProductVariant::where('sku', $sku)->firstOrFail()->product()->firstOrFail();
    }

    private function family(string $sku): ?BrandFamily
    {
        $id = $this->product($sku)->pko_brand_family_id;

        return $id === null ? null : BrandFamily::find($id);
    }

    public function test_creates_family_with_exact_code_and_label(): void
    {
        $record = $this->write([
            'reference' => 'FAM-1', 'name' => 'Sachet', 'manufacturer' => 'Marque A',
            'brand_family_code' => '  ACM ', 'brand_family_label' => 'Accessoires Mécaniques',
        ]);

        $this->assertSame(StagingStatus::Created, $record->status);
        $family = $this->family('FAM-1');
        $this->assertNotNull($family);
        $this->assertSame('ACM', $family->code);
        $this->assertSame('Accessoires Mécaniques', $family->label);
        $this->assertSame((int) $this->product('FAM-1')->brand_id, $family->brand_id);
    }

    public function test_same_code_is_shared_within_a_brand_and_distinct_across_brands(): void
    {
        $this->write(['reference' => 'A-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'MOT']);
        $this->write(['reference' => 'A-2', 'name' => 'P2', 'brand_name' => 'Marque A', 'brand_family_code' => 'MOT']);
        $this->write(['reference' => 'B-1', 'name' => 'P3', 'brand_name' => 'Marque B', 'brand_family_code' => 'MOT']);

        $this->assertSame($this->family('A-1')->id, $this->family('A-2')->id);
        $this->assertNotSame($this->family('A-1')->id, $this->family('B-1')->id);
        $this->assertSame(2, BrandFamily::count());
    }

    public function test_code_is_case_sensitive(): void
    {
        $this->write(['reference' => 'C-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'acm']);
        $this->write(['reference' => 'C-2', 'name' => 'P2', 'brand_name' => 'Marque A', 'brand_family_code' => 'ACM']);

        $this->assertSame('acm', $this->family('C-1')->code);
        $this->assertSame('ACM', $this->family('C-2')->code);
        $this->assertSame(2, BrandFamily::count());
    }

    public function test_reimport_updates_label_and_reassigns_family(): void
    {
        $this->write(['reference' => 'R-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'ACM', 'brand_family_label' => 'Ancien']);
        $this->write(['reference' => 'R-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'ACM', 'brand_family_label' => 'Nouveau']);
        $this->assertSame('Nouveau', $this->family('R-1')->label);

        $this->write(['reference' => 'R-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'SAV']);
        $this->assertSame('SAV', $this->family('R-1')->code);
    }

    public function test_blank_code_keeps_current_family(): void
    {
        $this->write(['reference' => 'K-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'ACM']);
        $this->write(['reference' => 'K-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => '']);

        $this->assertSame('ACM', $this->family('K-1')->code);
    }

    public function test_numeric_code_is_stored_as_string(): void
    {
        $this->write(['reference' => 'N-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 120]);

        $this->assertSame('120', $this->family('N-1')->code);
    }

    public function test_product_without_brand_is_written_without_family_and_warns(): void
    {
        $record = $this->write(['reference' => 'X-1', 'name' => 'P1', 'brand_family_code' => 'ACM']);

        $this->assertSame(StagingStatus::Created, $record->status);
        $this->assertNull($this->family('X-1'));
        $this->assertSame(0, BrandFamily::count());
        $this->assertTrue(ImportLog::where('message', 'like', '%produit sans marque%')->exists());
    }

    public function test_price_only_update_does_not_touch_family(): void
    {
        $this->write(['reference' => 'U-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'ACM']);
        $this->write(
            ['reference' => 'U-1', 'name' => 'P1', 'brand_name' => 'Marque A', 'brand_family_code' => 'MOT', 'price_cents' => 1000],
            ['update_mode' => 'price'],
        );

        $this->assertSame('ACM', $this->family('U-1')->code);
    }

    public function test_keys_are_canonical_and_selectable(): void
    {
        $this->assertContains('brand_family_code', LunarProductWriter::CANONICAL_KEYS);
        $this->assertContains('brand_family_label', LunarProductWriter::CANONICAL_KEYS);
        $this->assertArrayHasKey('brand_family_code', ProductFieldCatalog::flat());
        $this->assertArrayHasKey('brand_family_label', ProductFieldCatalog::flat());
    }
}
