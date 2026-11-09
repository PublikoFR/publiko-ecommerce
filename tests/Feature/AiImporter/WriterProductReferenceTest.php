<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Brand;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Tests\TestCase;

/**
 * Référence boutique à l'import : SKU = préfixe marque + MPN, rapprochement
 * sans doublon, MAJ tarifaire sans changement de référence.
 */
class WriterProductReferenceTest extends TestCase
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

    public function test_creates_composed_sku_and_keeps_mpn_exactly(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);

        $record = $this->write([
            'reference' => 'FOURN-1', 'name' => 'Moteur', 'manufacturer' => 'Somfy', 'mpn' => '001240385',
        ]);

        $this->assertSame(StagingStatus::Created, $record->status);
        $variant = ProductVariant::where('sku', 'SOM001240385')->firstOrFail();
        $this->assertSame('001240385', $variant->mpn);
        $this->assertFalse(ProductVariant::where('sku', 'FOURN-1')->exists());
    }

    public function test_composed_sku_without_reference_column(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);

        $record = $this->write(['name' => 'Moteur', 'brand_name' => 'Somfy', 'mpn' => '1240385']);

        $this->assertSame(StagingStatus::Created, $record->status);
        $this->assertTrue(ProductVariant::where('sku', 'SOM1240385')->exists());
    }

    public function test_forced_sku_wins_over_composition(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);

        $this->write(['sku' => 'SPECIAL-1', 'reference' => 'X', 'name' => 'Moteur', 'brand_name' => 'Somfy', 'mpn' => '1240385']);

        $this->assertTrue(ProductVariant::where('sku', 'SPECIAL-1')->exists());
        $this->assertFalse(ProductVariant::where('sku', 'SOM1240385')->exists());
    }

    public function test_brand_without_prefix_falls_back_to_reference(): void
    {
        Brand::create(['name' => 'Sans Prefixe']);

        $this->write(['reference' => 'REF-9', 'name' => 'Moteur', 'brand_name' => 'Sans Prefixe', 'mpn' => '77']);

        $this->assertSame('77', ProductVariant::where('sku', 'REF-9')->firstOrFail()->mpn);
    }

    public function test_brand_created_by_import_gets_default_prefix_when_free(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);

        $this->write(['reference' => 'R1', 'name' => 'A', 'brand_name' => 'Nicematic', 'mpn' => '12']);
        $this->write(['reference' => 'R2', 'name' => 'B', 'brand_name' => 'Somatic', 'mpn' => '34']);

        $this->assertSame('NIC', Brand::where('name', 'Nicematic')->firstOrFail()->pko_ref_prefix);
        $this->assertTrue(ProductVariant::where('sku', 'NIC12')->exists());
        // Collision SOM : marque créée sans préfixe, SKU = référence source.
        $this->assertNull(Brand::where('name', 'Somatic')->firstOrFail()->pko_ref_prefix);
        $this->assertTrue(ProductVariant::where('sku', 'R2')->exists());
    }

    public function test_price_reimport_matches_by_brand_and_mpn_without_duplicate_nor_reference_change(): void
    {
        $brand = Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);

        // Produit historique : SKU libre, sans préfixe.
        $this->write(['reference' => 'OLD-SKU', 'name' => 'Moteur', 'brand_name' => 'Somfy', 'sku' => 'OLD-SKU', 'mpn' => '01240385', 'price_cents' => 1000]);
        $this->assertSame(1, ProductVariant::count());

        $record = $this->write(
            ['reference' => 'AUTRE-REF', 'name' => 'Moteur renommé', 'brand_name' => 'Somfy', 'mpn' => '01240385', 'price_cents' => 2000],
            ['update_mode' => 'price', 'columns_to_import' => ['price_cents']],
        );

        $this->assertSame(StagingStatus::Updated, $record->status);
        $this->assertSame(1, ProductVariant::count());
        $this->assertSame(1, Product::count());
        $variant = ProductVariant::firstOrFail();
        $this->assertSame('OLD-SKU', $variant->sku);
        $this->assertSame('01240385', $variant->mpn);
        $this->assertSame(2000, (int) $variant->prices()->first()->price->value);
        $this->assertSame('Moteur', $variant->product->translateAttribute('name'));
        $this->assertSame((int) $brand->id, (int) $variant->product->brand_id);
    }

    public function test_reimport_matches_composed_sku_and_never_rewrites_it(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        $this->write(['name' => 'Moteur', 'brand_name' => 'Somfy', 'mpn' => '1240385', 'price_cents' => 1000]);

        $record = $this->write(['name' => 'Moteur', 'brand_name' => 'Somfy', 'mpn' => '1240385', 'price_cents' => 1500]);

        $this->assertSame(StagingStatus::Updated, $record->status);
        $this->assertSame(1, ProductVariant::count());
        $this->assertSame('SOM1240385', ProductVariant::firstOrFail()->sku);
    }

    public function test_leading_zeros_distinguish_mpn(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        $this->write(['name' => 'A', 'brand_name' => 'Somfy', 'mpn' => '0123']);
        $this->write(['name' => 'B', 'brand_name' => 'Somfy', 'mpn' => '123']);

        $this->assertSame(['SOM0123', 'SOM123'], ProductVariant::orderBy('sku')->pluck('sku')->all());
    }

    public function test_ambiguous_brand_and_mpn_is_rejected(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        $this->write(['sku' => 'A1', 'name' => 'A', 'brand_name' => 'Somfy', 'mpn' => '555']);
        // Un SKU forcé inconnu mais même marque + MPN rapproche (anti-doublon) :
        // la 2e variante ambiguë est donc posée directement (donnée historique).
        $first = ProductVariant::where('sku', 'A1')->firstOrFail();
        $second = $first->replicate();
        $second->sku = 'A2';
        $second->save();

        $record = $this->write(['name' => 'C', 'brand_name' => 'Somfy', 'mpn' => '555', 'price_cents' => 10]);

        $this->assertSame(StagingStatus::Error, $record->status);
        $this->assertStringContainsString('ambiguë', (string) $record->error_message);
        $this->assertSame(2, ProductVariant::count());
    }

    public function test_unknown_forced_sku_with_known_brand_and_mpn_updates_without_duplicate(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        $this->write(['name' => 'A', 'brand_name' => 'Somfy', 'mpn' => '555']);

        $record = $this->write(['sku' => 'NEW-1', 'name' => 'A', 'brand_name' => 'Somfy', 'mpn' => '555']);

        $this->assertSame(StagingStatus::Updated, $record->status);
        $this->assertSame(['SOM555'], ProductVariant::pluck('sku')->all());
    }

    public function test_row_without_any_reference_source_is_rejected(): void
    {
        $record = $this->write(['name' => 'Sans ref', 'mpn' => '1']);

        $this->assertSame(StagingStatus::Error, $record->status);
        $this->assertSame(0, ProductVariant::count());
    }
}
