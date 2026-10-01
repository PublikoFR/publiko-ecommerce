<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarBackupManager;
use Pko\AiImporter\Services\LunarProductWriter;
use Tests\TestCase;

/**
 * Cycle complet snapshot → import (création + mise à jour) → restore.
 */
class LunarBackupManagerTest extends TestCase
{
    use RefreshDatabase;

    private Collection $colA;

    private Collection $colB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();

        ProductType::firstOrCreate(['name' => 'Test Type']);
        TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);

        $group = CollectionGroup::create(['name' => 'Catalogue', 'handle' => 'catalogue']);
        $this->colA = Collection::create(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => 'A']))])]);
        $this->colB = Collection::create(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => 'B']))])]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function job(array $options = []): ImportJob
    {
        return ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'parsed',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
            'options' => $options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stage(ImportJob $job, array $data, int $row): StagingRecord
    {
        return StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => $row,
            'data' => $data,
            'status' => StagingStatus::Pending,
        ]);
    }

    /** Produit existant avant import, écrit par le writer comme en vrai. */
    private function seedExisting(): ProductVariant
    {
        $seedJob = $this->job();
        (new LunarProductWriter)->write($this->stage($seedJob, [
            'reference' => 'EXIST-1',
            'name' => 'Nom d\'origine',
            'ean' => '3000000000017',
            'mpn' => 'MPN-OLD',
            'price_cents' => 1000,
            'cost_price_cents' => 400,
            'stock' => 10,
            'min_quantity' => 2,
            'width_value' => 5,
        ], 1));

        $variant = ProductVariant::where('sku', 'EXIST-1')->firstOrFail();
        $variant->product->collections()->sync([$this->colA->id => ['position' => 3]]);

        return $variant;
    }

    private function nameOf(Product $product): ?string
    {
        $name = $product->fresh()->attribute_data->get('name');

        return $name?->getValue()->get('fr')?->getValue();
    }

    public function test_rollback_restores_updated_and_removes_created_products(): void
    {
        $variant = $this->seedExisting();
        $product = $variant->product;

        $job = $this->job();
        $this->stage($job, [
            'reference' => 'EXIST-1',
            'name' => 'Nom importé',
            'mpn' => 'MPN-NEW',
            'price_cents' => 9999,
            'cost_price_cents' => 777,
            'stock' => 99,
            'min_quantity' => 5,
            'width_value' => 42,
            'collections' => (string) $this->colB->id,
        ], 1);
        $created = $this->stage($job, [
            'reference' => 'NEW-1',
            'name' => 'Produit créé',
            'price_cents' => 500,
            'collections' => (string) $this->colA->id,
        ], 2);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));

        $job->refresh();
        $this->assertSame(ImportStatus::Imported, $job->import_status);
        $this->assertSame('Nom importé', $this->nameOf($product));
        $newProductId = $created->refresh()->lunar_product_id;
        $this->assertNotNull($newProductId);

        app(LunarBackupManager::class)->restore($job);

        // attribute_data lisible par le cast Lunar (régression toArray()).
        $this->assertSame('Nom d\'origine', $this->nameOf($product));

        $variant->refresh();
        $this->assertSame(1000, (int) $variant->prices()->first()->price->value);
        $this->assertSame(1, $variant->prices()->count());
        $this->assertSame(10, $variant->stock);
        $this->assertSame(2, $variant->min_quantity);
        $this->assertSame('MPN-OLD', $variant->mpn);
        $this->assertSame(400, (int) $variant->pko_cost_price);
        $this->assertEquals(5, $variant->width_value->value ?? $variant->width_value);

        // Pivot : exactement l'état initial (colB ajoutée par l'import retirée).
        $pivot = $product->fresh()->collections()->get()->mapWithKeys(fn ($c) => [$c->id => (int) $c->pivot->position])->all();
        $this->assertSame([$this->colA->id => 3], $pivot);

        // Produit créé : supprimé définitivement, variante comprise.
        $this->assertNull(Product::withTrashed()->find($newProductId));
        $this->assertFalse(ProductVariant::withTrashed()->where('sku', 'NEW-1')->exists());

        // Staging remis en état importable.
        $this->assertSame(
            [StagingStatus::Pending->value],
            StagingRecord::where('import_job_id', $job->id)->pluck('status')->map(fn ($s) => $s->value)->unique()->values()->all(),
        );
        $this->assertNull($created->refresh()->lunar_product_id);
        $this->assertTrue((bool) $job->fresh()->rollback_completed);
    }

    public function test_snapshot_covers_ean_join_column(): void
    {
        $variant = $this->seedExisting();

        $job = $this->job(['join_column' => 'ean']);
        // Référence différente du SKU : seule l'EAN relie la ligne au produit.
        $this->stage($job, [
            'reference' => 'REF-FOURNISSEUR',
            'ean13' => '3000000000017',
            'price_cents' => 2500,
            'stock' => 1,
        ], 1);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));
        $this->assertSame(2500, (int) $variant->fresh()->prices()->first()->price->value);

        app(LunarBackupManager::class)->restore($job->fresh());

        $variant->refresh();
        $this->assertSame(1000, (int) $variant->prices()->first()->price->value);
        $this->assertSame(10, $variant->stock);
    }

    public function test_price_created_by_import_on_existing_variant_is_removed(): void
    {
        $variant = $this->seedExisting();
        $variant->prices()->delete();

        $job = $this->job();
        $this->stage($job, ['reference' => 'EXIST-1', 'price_cents' => 1234], 1);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));
        $this->assertSame(1, $variant->prices()->count());

        app(LunarBackupManager::class)->restore($job->fresh());

        $this->assertSame(0, $variant->prices()->count());
    }
}
