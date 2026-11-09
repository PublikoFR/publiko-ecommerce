<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Tests\TestCase;

/**
 * Migration FEAT-003 : ne garder que la classe de TVA 20 %.
 */
class KeepSingleVatTaxClassMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassigns_variants_and_deletes_other_classes(): void
    {
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);
        $zone = TaxZone::create(['name' => 'FR', 'zone_type' => 'country', 'price_display' => 'tax_exclusive', 'active' => true, 'default' => true]);

        // Ids volontairement « mélangés » : la classe à garder n'est ni la
        // première, ni marquée default (le default est la classe sans taux).
        $orphan = TaxClass::create(['name' => 'Default Tax Class', 'default' => true]);
        $reduced = TaxClass::create(['name' => 'TVA 10%', 'default' => false]);
        $standard = TaxClass::create(['name' => 'TVA 20%', 'default' => false]);

        $standardRate = $this->rate($zone, 'Standard 20%', $standard, 20);
        $reducedRate = $this->rate($zone, 'Réduit 10%', $reduced, 10);

        $onOrphan = $this->variant('SKU-ORPHAN', $orphan);
        $onReduced = $this->variant('SKU-REDUCED', $reduced);
        $onStandard = $this->variant('SKU-STD', $standard);

        $this->runMigration();

        $this->assertSame([$standard->id], TaxClass::query()->pluck('id')->all());
        $this->assertTrue((bool) TaxClass::find($standard->id)->default);

        foreach ([$onOrphan, $onReduced, $onStandard] as $variant) {
            $this->assertSame($standard->id, $variant->refresh()->tax_class_id);
        }

        $this->assertSame([$standard->id], TaxRateAmount::query()->pluck('tax_class_id')->all());
        $this->assertNotNull(TaxRate::find($standardRate->id));
        $this->assertNull(TaxRate::find($reducedRate->id));

        // Idempotente : un second passage ne change rien.
        $this->runMigration();
        $this->assertSame([$standard->id], TaxClass::query()->pluck('id')->all());
    }

    public function test_leaves_classes_untouched_without_a_20_percent_rate(): void
    {
        TaxClass::create(['name' => 'Default Tax Class', 'default' => true]);
        TaxClass::create(['name' => 'Autre', 'default' => false]);

        $this->runMigration();

        $this->assertSame(2, TaxClass::query()->count());
    }

    private function runMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_09_000001_keep_single_vat_20_tax_class.php');
        $migration->up();
    }

    private function rate(TaxZone $zone, string $name, TaxClass $class, float $percentage): TaxRate
    {
        $rate = TaxRate::create(['tax_zone_id' => $zone->id, 'name' => $name, 'priority' => 1]);
        TaxRateAmount::create(['tax_rate_id' => $rate->id, 'tax_class_id' => $class->id, 'percentage' => $percentage]);

        return $rate;
    }

    private function variant(string $sku, TaxClass $class): ProductVariant
    {
        $type = ProductType::firstOrCreate(['name' => 'Test Type']);
        $product = Product::create(['product_type_id' => $type->id, 'status' => 'published', 'attribute_data' => collect()]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $class->id,
            'sku' => $sku,
            'shippable' => true,
            'stock' => 0,
            'unit_quantity' => 1,
        ]);
    }
}
