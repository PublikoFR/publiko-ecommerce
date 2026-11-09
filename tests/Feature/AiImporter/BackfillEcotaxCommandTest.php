<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
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

class BackfillEcotaxCommandTest extends TestCase
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
     * Simule un produit importé AVANT le support éco-taxe : prix sans éco-taxe,
     * colonne NULL, staging conservant `ecotax` (euros).
     *
     * @param  array<string, mixed>  $data
     */
    private function legacyImport(string $sku, int $priceCents, ?string $ecotax): ProductVariant
    {
        (new LunarProductWriter)->write(StagingRecord::create([
            'import_job_id' => $this->job->id,
            'row_number' => $this->row++,
            'data' => ['reference' => $sku, 'name' => 'Produit '.$sku, 'price_cents' => $priceCents],
            'status' => StagingStatus::Pending,
        ]));
        $variant = ProductVariant::where('sku', $sku)->firstOrFail();

        StagingRecord::query()->where('import_job_id', $this->job->id)->latest('id')->first()
            ->update(['data' => ['reference' => $sku, 'price_tex' => $priceCents / 100, 'ecotax' => $ecotax]]);

        return $variant;
    }

    private function prices(ProductVariant $variant): array
    {
        return $variant->prices()->orderBy('min_quantity')->get()->map(fn (Price $p) => (int) $p->price->value)->all();
    }

    public function test_dry_run_writes_nothing_and_prints_table(): void
    {
        $variant = $this->legacyImport('BF-1', 18652, '0,16');

        $this->assertSame(0, Artisan::call('ai-importer:backfill-ecotax', ['--dry-run' => true]));

        // Ligne du tableau : SKU | ancien prix | éco-taxe | nouveau prix.
        $this->assertMatchesRegularExpression('/BF-1\s*\|\s*186,52 €\s*\|\s*0,16 €\s*\|\s*186,68 €/u', Artisan::output());

        $variant->refresh();
        $this->assertNull($variant->pko_ecotax_cents);
        $this->assertSame([18652], $this->prices($variant));
    }

    public function test_backfill_adds_ecotax_to_every_tier_and_is_idempotent(): void
    {
        $variant = $this->legacyImport('BF-2', 18652, '0.16');
        $variant->prices()->create([
            'currency_id' => Currency::getDefault()->id,
            'customer_group_id' => CustomerGroup::factory()->create()->id,
            'min_quantity' => 10,
            'price' => 17000,
        ]);
        $untouched = $this->legacyImport('BF-3', 1000, '0');

        $this->artisan('ai-importer:backfill-ecotax')->assertSuccessful();

        $variant->refresh();
        $this->assertSame(16, (int) $variant->pko_ecotax_cents);
        $this->assertSame([18668, 17016], $this->prices($variant));
        $this->assertNull($untouched->refresh()->pko_ecotax_cents);
        $this->assertSame([1000], $this->prices($untouched));

        // Deuxième passage : aucune double addition.
        $this->artisan('ai-importer:backfill-ecotax')->assertSuccessful();
        $this->assertSame([18668, 17016], $this->prices($variant->refresh()));
    }

    public function test_negotiated_prices_are_listed_not_modified(): void
    {
        $variant = $this->legacyImport('BF-4', 5000, '0.19');
        $customerId = DB::table('lunar_customers')->insertGetId([
            'first_name' => 'A', 'last_name' => 'B', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pko_negotiated_prices')->insert([
            'customer_id' => $customerId,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 4500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ai-importer:backfill-ecotax')
            ->expectsOutputToContain('Prix négociés NON modifiés')
            ->expectsOutputToContain('45,00 €')
            ->assertSuccessful();

        $this->assertSame(4500, (int) DB::table('pko_negotiated_prices')->value('price'));
        $this->assertSame([5019], $this->prices($variant->refresh()));
    }
}
