<?php

declare(strict_types=1);

namespace Tests\Feature\CatalogFeatures;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Models\BrandFamily;
use Tests\TestCase;

/**
 * Famille fabricant (privée) dans la fiche produit admin.
 */
class EditProductUnifiedBrandFamilyTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Brand $brandA;

    private Brand $brandB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $admin = Staff::query()->first();
        $this->assertNotNull($admin, 'Un Staff admin seedé est requis.');
        $this->actingAs($admin, 'staff');

        $this->brandA = Brand::create(['name' => 'Marque Famille A']);
        $this->brandB = Brand::create(['name' => 'Marque Famille B']);

        $this->product = Product::query()->firstOrFail();
        $this->product->brand_id = $this->brandA->id;
        $this->product->save();
    }

    public function test_loads_and_saves_family_of_the_product_brand(): void
    {
        $acm = BrandFamily::create(['brand_id' => $this->brandA->id, 'code' => 'ACM', 'label' => 'Accessoires']);
        $mot = BrandFamily::create(['brand_id' => $this->brandA->id, 'code' => 'MOT', 'label' => 'Moteur']);
        BrandFamily::create(['brand_id' => $this->brandB->id, 'code' => 'ZZZ']);

        $this->product->pko_brand_family_id = $acm->id;
        $this->product->save();

        $page = Livewire::test(EditProductUnified::class, ['record' => $this->product->id])
            ->assertSet('brandFamilyId', $acm->id);

        $this->assertSame(['ACM', 'MOT'], $page->instance()->brandFamilyOptions->pluck('code')->all());

        $page->set('brandFamilyId', $mot->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($mot->id, (int) $this->product->refresh()->pko_brand_family_id);
    }

    public function test_changing_brand_clears_family(): void
    {
        $acm = BrandFamily::create(['brand_id' => $this->brandA->id, 'code' => 'ACM']);
        $this->product->pko_brand_family_id = $acm->id;
        $this->product->save();

        Livewire::test(EditProductUnified::class, ['record' => $this->product->id])
            ->set('brandId', $this->brandB->id)
            ->assertSet('brandFamilyId', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->product->refresh()->pko_brand_family_id);
    }

    public function test_family_of_another_brand_is_never_persisted(): void
    {
        $foreign = BrandFamily::create(['brand_id' => $this->brandB->id, 'code' => 'ZZZ']);

        Livewire::test(EditProductUnified::class, ['record' => $this->product->id])
            ->set('brandFamilyId', $foreign->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->product->refresh()->pko_brand_family_id);
    }
}
