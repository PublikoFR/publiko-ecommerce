<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
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
use Tests\TestCase;

/**
 * Couvre les options de job honorées par LunarProductWriter::configure() :
 * update_mode (all|price|stock|price_stock) et row_filter (all|missing|existing).
 */
class WriterOptionsTest extends TestCase
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

    private function job(): ImportJob
    {
        return ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'pending',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(ImportJob $job, array $data, int $rowNumber = 1): StagingRecord
    {
        return StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => $rowNumber,
            'data' => $data,
            'status' => StagingStatus::Pending,
        ]);
    }

    private function seedProduct(ImportJob $job, string $sku): void
    {
        (new LunarProductWriter)->write($this->record($job, [
            'reference' => $sku,
            'name' => 'Original',
            'price_cents' => 1000,
            'stock' => 10,
        ]));
    }

    private function nameOf(string $sku): ?string
    {
        $variant = ProductVariant::where('sku', $sku)->firstOrFail();
        $value = $variant->product->attribute_data->get('name')?->getValue()['fr'] ?? null;

        return $value instanceof Text ? $value->getValue() : $value;
    }

    private function priceCentsOf(string $sku): int
    {
        $variantId = ProductVariant::where('sku', $sku)->value('id');
        $price = Price::where('priceable_id', $variantId)->where('priceable_type', ProductVariant::class)->first();

        return (int) $price->price->value;
    }

    public function test_update_mode_price_only_touches_price(): void
    {
        $job = $this->job();
        $this->seedProduct($job, 'SKU-PRICE');

        $rec = $this->record($job, [
            'reference' => 'SKU-PRICE',
            'name' => 'Changed',
            'price_cents' => 5000,
            'stock' => 99,
        ], 2);

        (new LunarProductWriter)->configure(['update_mode' => 'price'])->write($rec);

        $rec->refresh();
        $this->assertSame(StagingStatus::Updated, $rec->status);
        $this->assertSame(5000, $this->priceCentsOf('SKU-PRICE'));
        $this->assertSame(10, ProductVariant::where('sku', 'SKU-PRICE')->value('stock')); // inchangé
    }

    public function test_update_mode_stock_only_touches_stock(): void
    {
        $job = $this->job();
        $this->seedProduct($job, 'SKU-STOCK');

        $rec = $this->record($job, [
            'reference' => 'SKU-STOCK',
            'name' => 'Changed',
            'price_cents' => 5000,
            'stock' => 99,
        ], 2);

        (new LunarProductWriter)->configure(['update_mode' => 'stock'])->write($rec);

        $this->assertSame(99, ProductVariant::where('sku', 'SKU-STOCK')->value('stock'));
        $this->assertSame(1000, $this->priceCentsOf('SKU-STOCK')); // inchangé
    }

    public function test_update_mode_price_stock_touches_both_not_name(): void
    {
        $job = $this->job();
        $this->seedProduct($job, 'SKU-PS');

        $rec = $this->record($job, [
            'reference' => 'SKU-PS',
            'name' => 'Changed',
            'price_cents' => 5000,
            'stock' => 99,
        ], 2);

        (new LunarProductWriter)->configure(['update_mode' => 'price_stock'])->write($rec);

        $this->assertSame(99, ProductVariant::where('sku', 'SKU-PS')->value('stock'));
        $this->assertSame(5000, $this->priceCentsOf('SKU-PS'));

        // Le nom (attribute_data) ne doit pas avoir bougé en mode price_stock.
        $this->assertSame('Original', $this->nameOf('SKU-PS'));
    }

    public function test_update_mode_all_updates_everything(): void
    {
        $job = $this->job();
        $this->seedProduct($job, 'SKU-ALL');

        $rec = $this->record($job, [
            'reference' => 'SKU-ALL',
            'name' => 'Changed',
            'price_cents' => 5000,
            'stock' => 99,
        ], 2);

        (new LunarProductWriter)->configure(['update_mode' => 'all'])->write($rec);

        $this->assertSame(99, ProductVariant::where('sku', 'SKU-ALL')->value('stock'));
        $this->assertSame(5000, $this->priceCentsOf('SKU-ALL'));
        $this->assertSame('Changed', $this->nameOf('SKU-ALL'));
    }

    public function test_row_filter_missing_skips_existing_product(): void
    {
        $job = $this->job();
        $this->seedProduct($job, 'SKU-EXISTS');

        $rec = $this->record($job, [
            'reference' => 'SKU-EXISTS',
            'name' => 'Changed',
            'price_cents' => 5000,
            'stock' => 99,
        ], 2);

        (new LunarProductWriter)->configure(['row_filter' => 'missing_supplier_ref'])->write($rec);

        $rec->refresh();
        $this->assertSame(StagingStatus::Skipped, $rec->status);
        $this->assertSame(1000, $this->priceCentsOf('SKU-EXISTS')); // rien écrit
        $this->assertSame(10, ProductVariant::where('sku', 'SKU-EXISTS')->value('stock'));
    }

    public function test_row_filter_existing_skips_new_product(): void
    {
        $job = $this->job();

        $rec = $this->record($job, [
            'reference' => 'SKU-NEW',
            'name' => 'Nouveau',
            'price_cents' => 5000,
            'stock' => 99,
        ]);

        (new LunarProductWriter)->configure(['row_filter' => 'existing_supplier_ref'])->write($rec);

        $rec->refresh();
        $this->assertSame(StagingStatus::Skipped, $rec->status);
        $this->assertNull(ProductVariant::where('sku', 'SKU-NEW')->first()); // pas de création
    }

    public function test_row_filter_missing_allows_creation(): void
    {
        $job = $this->job();

        $rec = $this->record($job, [
            'reference' => 'SKU-CREATE',
            'name' => 'Nouveau',
            'price_cents' => 5000,
            'stock' => 99,
        ]);

        (new LunarProductWriter)->configure(['row_filter' => 'missing_supplier_ref'])->write($rec);

        $rec->refresh();
        $this->assertSame(StagingStatus::Created, $rec->status);
        $this->assertSame(99, ProductVariant::where('sku', 'SKU-CREATE')->value('stock'));
    }
}
