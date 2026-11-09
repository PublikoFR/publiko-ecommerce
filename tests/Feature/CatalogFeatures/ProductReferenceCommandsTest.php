<?php

declare(strict_types=1);

namespace Tests\Feature\CatalogFeatures;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Brand;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

/**
 * `pko:brands:ref-prefixes` et `pko:references:sync`.
 */
class ProductReferenceCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Brand/Product génèrent une URL : langue par défaut requise.
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);
    }

    private function variant(?Brand $brand, ?string $sku, ?string $mpn): ProductVariant
    {
        $product = Product::create([
            'product_type_id' => ProductType::firstOrCreate(['name' => 'T'])->id,
            'status' => 'published',
            'brand_id' => $brand?->id,
            'attribute_data' => collect(),
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true])->id,
            'sku' => $sku,
            'mpn' => $mpn,
            'stock' => 0,
            'shippable' => true,
            'purchasable' => 'always',
        ]);
    }

    public function test_prefix_proposal_lists_collisions_and_never_overwrites(): void
    {
        $existing = Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SFY']);
        $somatic = Brand::create(['name' => 'Somatic']);
        $sombra = Brand::create(['name' => 'Sombra']);
        $nice = Brand::create(['name' => 'Nice']);
        $taken = Brand::create(['name' => 'Sfyx']); // SFY déjà pris par Somfy

        $this->artisan('pko:brands:ref-prefixes')
            ->expectsOutputToContain('Collisions')
            ->assertSuccessful();
        $this->assertNull($nice->refresh()->pko_ref_prefix, 'Sans --apply : rapport seul.');

        $this->artisan('pko:brands:ref-prefixes --apply')->assertSuccessful();

        $this->assertSame('SFY', $existing->refresh()->pko_ref_prefix);
        $this->assertSame('NIC', $nice->refresh()->pko_ref_prefix);
        $this->assertNull($somatic->refresh()->pko_ref_prefix);
        $this->assertNull($sombra->refresh()->pko_ref_prefix);
        $this->assertNull($taken->refresh()->pko_ref_prefix);
    }

    public function test_sync_dry_run_reports_without_writing(): void
    {
        $somfy = Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        $bare = Brand::create(['name' => 'Sans']);

        $toUpdate = $this->variant($somfy, 'OLD-1', '01240385');
        $conform = $this->variant($somfy, 'SOM777', '777');
        $this->variant($bare, 'X1', '1');
        $this->variant($somfy, 'NOMPN', null);
        $this->variant($somfy, 'SOM888', 'zzz');       // porte la cible d'une autre
        $blocked = $this->variant($somfy, 'B-1', '888'); // → cible SOM888 déjà prise

        $this->artisan('pko:references:sync --dry-run')
            ->expectsTable(['Catégorie', 'Variantes'], [
                ['À modifier', 2],                    // OLD-1 et SOM888 (→ SOMzzz)
                ['Déjà conformes', 1],
                ['Marque absente ou sans préfixe', 1],
                ['MPN vide', 1],
                ['SKU cible déjà pris / ambigu', 1],
            ])
            ->assertSuccessful();

        $this->assertSame('OLD-1', $toUpdate->refresh()->sku);
        $this->assertSame('B-1', $blocked->refresh()->sku);
        $this->assertSame('SOM777', $conform->refresh()->sku);
    }

    public function test_sync_rewrites_only_free_targets_and_keeps_mpn(): void
    {
        $somfy = Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        $toUpdate = $this->variant($somfy, 'OLD-1', '01240385');
        $dupA = $this->variant($somfy, 'D-A', '555');
        $dupB = $this->variant($somfy, 'D-B', '555');

        $this->artisan('pko:references:sync --force')->assertSuccessful();

        $toUpdate->refresh();
        $this->assertSame('SOM01240385', $toUpdate->sku);
        $this->assertSame('01240385', $toUpdate->mpn);
        $this->assertSame('D-A', $dupA->refresh()->sku);
        $this->assertSame('D-B', $dupB->refresh()->sku);
    }
}
