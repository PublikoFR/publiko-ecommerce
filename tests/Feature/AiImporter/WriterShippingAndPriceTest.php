<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\ShippingCommon\Models\Supplier;
use Tests\TestCase;

/**
 * Règles de frais de port et prix de base à l'import : la valeur en place est
 * REMPLACÉE quand la source en fournit une, CONSERVÉE quand elle est vide —
 * jamais de tarif ni de fournisseur supplémentaire.
 */
class WriterShippingAndPriceTest extends TestCase
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

    /** @param  array<string, mixed>  $data */
    private function write(array $data, array $options = []): StagingRecord
    {
        $record = StagingRecord::create([
            'import_job_id' => $this->job->id,
            'row_number' => $this->row++,
            'data' => $data,
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->configure($options)->setJobId($this->job->id)->write($record);

        return $record->refresh();
    }

    private function variant(string $sku): ProductVariant
    {
        return ProductVariant::where('sku', $sku)->firstOrFail();
    }

    private function product(string $sku): Product
    {
        return $this->variant($sku)->product->refresh();
    }

    private function warnings(): int
    {
        return ImportLog::where('import_job_id', $this->job->id)->where('level', 'warning')->count();
    }

    // ── Frais de port ────────────────────────────────────────────────────────

    public function test_flat_transport_price_sets_flat_mode_and_amount(): void
    {
        $this->write(['reference' => 'PORT-1', 'name' => 'Portail', 'transport_price_cents' => 4500]);

        $product = $this->product('PORT-1');
        $this->assertSame('flat', $product->pko_port_mode);
        $this->assertSame(4500, (int) $product->pko_transport_price_cents);
    }

    public function test_legacy_additional_shipping_cost_in_euros_is_converted(): void
    {
        $this->write(['reference' => 'PORT-2', 'name' => 'Portail', 'additional_shipping_cost' => '12,50']);

        $product = $this->product('PORT-2');
        $this->assertSame('flat', $product->pko_port_mode);
        $this->assertSame(1250, (int) $product->pko_transport_price_cents);
    }

    public function test_reimport_overwrites_the_flat_price_instead_of_adding_one(): void
    {
        $this->write(['reference' => 'PORT-3', 'name' => 'Portail', 'transport_price_cents' => 4500]);
        $this->write(['reference' => 'PORT-3', 'name' => 'Portail', 'transport_price_cents' => 6000]);

        $product = $this->product('PORT-3');
        $this->assertSame('flat', $product->pko_port_mode);
        $this->assertSame(6000, (int) $product->pko_transport_price_cents);
    }

    public function test_zero_or_blank_shipping_cost_leaves_existing_settings_untouched(): void
    {
        $this->write(['reference' => 'PORT-4', 'name' => 'Portail', 'transport_price_cents' => 4500]);

        // « 0 » (défaut des configs PrestaShop), vide, mode vide : rien ne bouge.
        $this->write(['reference' => 'PORT-4', 'name' => 'Portail', 'additional_shipping_cost' => '0', 'port_mode' => '']);
        $this->write(['reference' => 'PORT-4', 'name' => 'Portail', 'transport_price_cents' => '', 'logistics_class' => '']);

        $product = $this->product('PORT-4');
        $this->assertSame('flat', $product->pko_port_mode);
        $this->assertSame(4500, (int) $product->pko_transport_price_cents);
    }

    public function test_zero_shipping_cost_on_creation_gives_the_default_mode(): void
    {
        $this->write(['reference' => 'PORT-5', 'name' => 'Portail', 'additional_shipping_cost' => '0']);

        $product = $this->product('PORT-5');
        $this->assertSame('standard', $product->pko_port_mode);
        $this->assertNull($product->pko_transport_price_cents);
    }

    public function test_unknown_port_mode_is_ignored_with_a_warning(): void
    {
        $this->write(['reference' => 'PORT-6', 'name' => 'Portail', 'port_mode' => 'free']);
        $this->write(['reference' => 'PORT-6', 'name' => 'Portail', 'port_mode' => 'gratuit']);

        $this->assertSame('free', $this->product('PORT-6')->pko_port_mode);
        $this->assertSame(1, $this->warnings());
    }

    public function test_explicit_non_flat_mode_clears_the_flat_price(): void
    {
        $this->write(['reference' => 'PORT-7', 'name' => 'Portail', 'transport_price_cents' => 4500]);
        $this->write(['reference' => 'PORT-7', 'name' => 'Portail', 'port_mode' => 'quote']);

        $product = $this->product('PORT-7');
        $this->assertSame('quote', $product->pko_port_mode);
        $this->assertNull($product->pko_transport_price_cents);
    }

    public function test_price_only_update_never_touches_shipping_rules(): void
    {
        $this->write(['reference' => 'PORT-8', 'name' => 'Portail', 'price_cents' => 1000, 'port_mode' => 'free']);
        $this->write(
            ['reference' => 'PORT-8', 'name' => 'Portail', 'price_cents' => 1200, 'transport_price_cents' => 4500],
            ['update_mode' => 'price'],
        );

        $product = $this->product('PORT-8');
        $this->assertSame('free', $product->pko_port_mode);
        $this->assertNull($product->pko_transport_price_cents);
    }

    public function test_supplier_is_matched_case_insensitively_and_never_duplicated(): void
    {
        $existing = Supplier::create(['name' => 'SOMFY', 'port_inclus' => 'oui']);

        $this->write(['reference' => 'SUP-1', 'name' => 'Moteur', 'supplier' => 'Somfy']);
        $this->write(['reference' => 'SUP-2', 'name' => 'Moteur', 'supplier' => '  somfy ']);

        $this->assertSame(1, Supplier::count());
        $this->assertSame($existing->id, (int) $this->product('SUP-1')->pko_supplier_id);
        $this->assertSame('inherit', $this->product('SUP-2')->pko_port_mode);
    }

    // ── Prix de base ─────────────────────────────────────────────────────────

    public function test_reimport_updates_the_base_price_row_in_place(): void
    {
        $this->write(['reference' => 'PRIX-1', 'name' => 'Moteur', 'price_cents' => 1000]);
        $this->write(['reference' => 'PRIX-1', 'name' => 'Moteur', 'price_cents' => 1250]);

        $prices = $this->variant('PRIX-1')->prices()->get();
        $this->assertCount(1, $prices);
        $this->assertSame(1250, (int) $prices->first()->price->value);
    }

    public function test_duplicate_and_phantom_base_prices_are_merged_into_one(): void
    {
        $this->write(['reference' => 'PRIX-2', 'name' => 'Moteur', 'price_cents' => 1000]);
        $variant = $this->variant('PRIX-2');
        $base = $variant->prices()->first()->getRawOriginal();
        unset($base['id']);

        // Doublon strict + ligne « fantôme » (FQCN au lieu de l'alias morph).
        DB::table('lunar_prices')->insert($base);
        DB::table('lunar_prices')->insert(['priceable_type' => ProductVariant::class] + $base);
        $this->assertSame(3, Price::where('priceable_id', $variant->id)->count());

        $this->write(['reference' => 'PRIX-2', 'name' => 'Moteur', 'price_cents' => 1500]);

        $rows = Price::where('priceable_id', $variant->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($variant->getMorphClass(), $rows->first()->getRawOriginal('priceable_type'));
        $this->assertSame(1500, (int) $rows->first()->price->value);
    }

    public function test_customer_group_and_quantity_tiers_are_left_alone(): void
    {
        $this->write(['reference' => 'PRIX-3', 'name' => 'Moteur', 'price_cents' => 1000]);
        $variant = $this->variant('PRIX-3');
        $currencyId = Currency::where('default', true)->value('id');
        $group = CustomerGroup::firstOrCreate(['handle' => 'pro'], ['name' => 'Pro', 'default' => false]);

        $variant->prices()->create(['currency_id' => $currencyId, 'customer_group_id' => $group->id, 'min_quantity' => 1, 'price' => 900]);
        $variant->prices()->create(['currency_id' => $currencyId, 'customer_group_id' => null, 'min_quantity' => 10, 'price' => 800]);

        $this->write(['reference' => 'PRIX-3', 'name' => 'Moteur', 'price_cents' => 1100]);

        $prices = $variant->prices()->orderBy('id')->get();
        $this->assertCount(3, $prices);
        $this->assertSame([1100, 900, 800], $prices->map(fn (Price $p): int => (int) $p->price->value)->all());
    }

    // ── Éco-participation et colonnes à importer ─────────────────────────────

    public function test_ecotax_follows_the_price_when_columns_are_restricted(): void
    {
        $this->write(
            ['reference' => 'ECO-COL', 'name' => 'Moteur', 'price_tex' => '100', 'ecotax' => '0,20'],
            ['columns_to_import' => ['name', 'price_cents']],
        );

        $variant = $this->variant('ECO-COL');
        $this->assertSame(10020, (int) $variant->prices()->first()->price->value);
        $this->assertSame(20, (int) $variant->pko_ecotax_cents);
    }

    public function test_ecotax_above_the_price_is_ignored_with_a_warning(): void
    {
        $this->write(['reference' => 'ECO-KO', 'name' => 'Moteur', 'price_cents' => 100, 'ecotax_cents' => 500]);

        $this->assertNull($this->variant('ECO-KO')->pko_ecotax_cents);
        $this->assertSame(1, $this->warnings());
    }

    // ── Atomicité d'une ligne ────────────────────────────────────────────────

    public function test_a_failing_row_leaves_no_half_created_product(): void
    {
        $before = Product::count();

        try {
            // EAN trop long pour la colonne : la variante ne peut pas être créée.
            $this->write(['reference' => 'ATOM-1', 'name' => 'Moteur', 'ean' => str_repeat('9', 400)]);
            $this->fail('La ligne aurait dû échouer.');
        } catch (QueryException) {
            // attendu
        }

        $this->assertSame($before, Product::count());
        $this->assertSame(0, ProductVariant::where('sku', 'ATOM-1')->count());
    }
}
