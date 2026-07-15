<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\ProductVideos\Models\ProductVideo;
use Pko\ShippingCommon\Models\Supplier;
use Tests\TestCase;

class LunarProductWriterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProductType::firstOrCreate(['name' => 'Test Type']);
        TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);
    }

    public function test_creates_product_and_variant_with_price(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'pending',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 2,
            'data' => [
                'reference' => 'SKU-100',
                'name' => 'Moteur Somfy RS100',
                'description' => 'Moteur tubulaire 40Nm',
                'price_cents' => 19900,
                'stock' => 12,
                'weight_value' => 2.5,
            ],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $record->refresh();
        $this->assertSame(StagingStatus::Created, $record->status);

        $variant = ProductVariant::where('sku', 'SKU-100')->firstOrFail();
        $this->assertSame(12, $variant->stock);
        $this->assertEquals(2.5, $variant->weight_value);

        // Via la relation Lunar (alias morph `product_variant`) : garantit que le
        // prix est retrouvable par l'app, pas seulement présent en base.
        $price = $variant->prices()->first();
        $this->assertNotNull($price);
    }

    public function test_second_call_updates_instead_of_creates(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'pending',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        $mkRecord = fn (int $stock) => StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => $stock,
            'data' => [
                'reference' => 'SKU-200',
                'name' => 'Stock test',
                'price_cents' => 1000,
                'stock' => $stock,
            ],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($mkRecord(10));
        $r2 = $mkRecord(42);
        (new LunarProductWriter)->write($r2);

        $r2->refresh();
        $this->assertSame(StagingStatus::Updated, $r2->status);
        $this->assertSame(42, ProductVariant::where('sku', 'SKU-200')->value('stock'));
    }

    public function test_missing_reference_marks_error(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);
        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 1,
            'data' => ['name' => 'no sku'],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $record->refresh();
        $this->assertSame(StagingStatus::Error, $record->status);
        $this->assertStringContainsString('reference', (string) $record->error_message);
    }

    public function test_accepts_prestashop_legacy_keys(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);

        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 2,
            'data' => [
                'reference' => 'SOM-LEGACY-1',
                'name' => 'Produit legacy PS',
                'price_tex' => 199.00,     // → price_cents = 19900
                'quantity' => 7,            // → stock
                'ean13' => '3017620422003', // → ean
                'manufacturer' => 'Somfy',  // → brand_name
                'link_rewrite' => 'produit-legacy-ps', // → url_key
                'depth' => 12.5,            // → length_value
                'width' => 3.2,             // → width_value
                'height' => 4.8,            // → height_value
                'weight' => 0.450,          // → weight_value
            ],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $record->refresh();
        $this->assertSame(StagingStatus::Created, $record->status);

        $variant = ProductVariant::where('sku', 'SOM-LEGACY-1')->firstOrFail();
        $this->assertSame(7, $variant->stock);
        $this->assertSame('3017620422003', $variant->ean);
        $this->assertEquals(0.450, $variant->weight_value);
        $this->assertEquals(12.5, $variant->length_value);
        $this->assertEquals(3.2, $variant->width_value);
        $this->assertEquals(4.8, $variant->height_value);

        $price = Price::where('priceable_id', $variant->id)->first();
        $this->assertNotNull($price);
        $this->assertSame(19900, (int) $price->price->value);
    }

    public function test_imports_tags_mpn_min_quantity_and_supplier(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);

        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 2,
            'data' => [
                'reference' => 'SOM-EXTRA-1',
                'name' => 'Produit extra',
                'tags' => 'SOMFY,670002,Accessoire',
                'mpn' => 'MPN-123',
                'minimal_quantity' => 6,   // → min_quantity
                'supplier' => 'Somfy Distribution',
            ],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $variant = ProductVariant::where('sku', 'SOM-EXTRA-1')->firstOrFail();
        $this->assertSame('MPN-123', $variant->mpn);
        $this->assertSame(6, (int) $variant->min_quantity);

        $product = $variant->product;
        // Lunar normalise les valeurs de tag en MAJUSCULES.
        $this->assertSame(['SOMFY', '670002', 'ACCESSOIRE'], $product->tags->pluck('value')->all());

        $supplier = Supplier::where('name', 'Somfy Distribution')->first();
        $this->assertNotNull($supplier);
        $this->assertSame((int) $supplier->id, (int) $product->pko_supplier_id);
    }

    public function test_imports_wholesale_price_as_cost_price_cents(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);

        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 2,
            'data' => [
                'reference' => 'SOM-COST-1',
                'name' => 'Sachet visserie',
                'wholesale_price' => 2.7, // euros → cost_price_cents = 270
            ],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $variant = ProductVariant::where('sku', 'SOM-COST-1')->firstOrFail();
        $this->assertSame(270, (int) $variant->pko_cost_price);
    }

    public function test_imports_videos_from_json_object_format(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);

        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 2,
            'data' => [
                'reference' => 'SOM-VID-1',
                'name' => 'Moteur avec vidéo',
                // Format prépa PrestaShop : string JSON [{url,title}] (virgules dans
                // l'URL et le titre → un explode(',') naïf casse le parse).
                'videos' => '[{"url":"https://www.youtube.com/watch?v=VtL8StaDb50","title":"RS100 io hybrid, test pro | Somfy"}]',
            ],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $variant = ProductVariant::where('sku', 'SOM-VID-1')->firstOrFail();
        $videos = ProductVideo::where('product_id', $variant->product_id)->get();

        $this->assertCount(1, $videos);
        $this->assertSame('https://www.youtube.com/watch?v=VtL8StaDb50', $videos->first()->url);
        $this->assertSame('RS100 io hybrid, test pro | Somfy', $videos->first()->title);
    }

    public function test_canonical_key_wins_over_legacy(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);

        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 3,
            'data' => [
                'reference' => 'SOM-CANON-1',
                'name' => 'Test priority',
                'price_cents' => 5000,  // canonical wins
                'price_tex' => 99.0,    // legacy ignored
                'stock' => 10,           // canonical wins
                'quantity' => 999,        // legacy ignored
            ],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $variant = ProductVariant::where('sku', 'SOM-CANON-1')->firstOrFail();
        $this->assertSame(10, $variant->stock);

        $price = Price::where('priceable_id', $variant->id)->first();
        $this->assertSame(5000, (int) $price->price->value);
    }

    public function test_import_defaults_logistics_class_to_b(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);
        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 1,
            'data' => ['reference' => 'SKU-LOGB', 'name' => 'Produit classe B par défaut', 'price_cents' => 1000],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $product = ProductVariant::where('sku', 'SKU-LOGB')->firstOrFail()->product;
        $this->assertSame('B', $product->pko_logistics_class);
    }

    public function test_import_respects_explicit_logistics_class(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);
        $record = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 1,
            'data' => ['reference' => 'SKU-LOGA', 'name' => 'Produit classe A', 'price_cents' => 1000, 'logistics_class' => 'A'],
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->write($record);

        $product = ProductVariant::where('sku', 'SKU-LOGA')->firstOrFail()->product;
        $this->assertSame('A', $product->pko_logistics_class);
    }

    public function test_update_does_not_overwrite_logistics_class_if_absent_from_source(): void
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);

        // Création avec classe A explicite.
        $r1 = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 1,
            'data' => ['reference' => 'SKU-LOG-UPD', 'name' => 'Test', 'price_cents' => 1000, 'logistics_class' => 'A'],
            'status' => StagingStatus::Pending,
        ]);
        (new LunarProductWriter)->write($r1);

        // Mise à jour sans logistics_class → ne doit pas changer la classe.
        $r2 = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 2,
            'data' => ['reference' => 'SKU-LOG-UPD', 'name' => 'Test modifié', 'price_cents' => 2000],
            'status' => StagingStatus::Pending,
        ]);
        (new LunarProductWriter)->write($r2);

        $product = ProductVariant::where('sku', 'SKU-LOG-UPD')->firstOrFail()->product;
        $this->assertSame('A', $product->pko_logistics_class);
    }
}
