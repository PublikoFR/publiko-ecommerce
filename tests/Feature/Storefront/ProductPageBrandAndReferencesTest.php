<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Pko\StorefrontCms\Models\Setting;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Fiche produit : logo du fabricant (repli texte) et deux références
 * distinctes (réf. boutique = SKU, réf. fabricant = MPN).
 */
class ProductPageBrandAndReferencesTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Setting::set('brand.name', 'Boutique Test');

        $product = Product::query()->has('urls')->with(['defaultUrl', 'variants'])->first();
        $this->assertNotNull($product, 'Seeded products with a URL are required.');

        $this->brand = Brand::query()->create(['name' => 'Fabricant Démo']);
        $product->update(['brand_id' => $this->brand->id]);

        // Une seule variante (pas d'options) pour cibler la variante affichée.
        $product->variants->skip(1)->each->delete();
        $product->variants->first()->update(['sku' => 'SKU-BTQ-001', 'mpn' => 'MPN-FAB-777']);

        $this->product = $product->fresh(['defaultUrl']);
    }

    private function visit(): TestResponse
    {
        return $this->get('/produits/'.$this->product->defaultUrl->slug)->assertOk();
    }

    private function attachLogo(Brand $brand): Media
    {
        return Media::query()->create([
            'model_type' => $brand->getMorphClass(),
            'model_id' => $brand->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'images',
            'name' => 'logo',
            'file_name' => 'logo-fabricant.png',
            'mime_type' => 'image/png',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => ['primary' => true],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);
    }

    public function test_brand_logo_is_displayed_when_present(): void
    {
        $this->attachLogo($this->brand);

        $this->visit()
            ->assertSee('data-brand-logo', false)
            ->assertSee('logo-fabricant.png', false)
            ->assertSee('alt="Logo Fabricant Démo"', false)
            ->assertDontSee('data-brand-name', false);
    }

    public function test_brand_name_text_fallback_without_logo(): void
    {
        $this->visit()
            ->assertDontSee('data-brand-logo', false)
            ->assertSee('data-brand-name', false)
            ->assertSee('Fabricant Démo');
    }

    public function test_brand_links_to_brand_page(): void
    {
        $this->visit()
            ->assertSee(route('brand.view', ['slug' => Str::slug('Fabricant Démo')]), false);
    }

    public function test_both_references_are_displayed_with_labels(): void
    {
        $this->visit()
            ->assertSeeInOrder(['Réf. Boutique Test', 'SKU-BTQ-001'])
            ->assertSeeInOrder(['Réf. fabricant', 'MPN-FAB-777']);
    }

    public function test_manufacturer_reference_hidden_when_mpn_is_null(): void
    {
        $this->product->variants()->update(['mpn' => null]);

        $this->visit()
            ->assertSee('SKU-BTQ-001')
            ->assertDontSee('Réf. fabricant')
            ->assertDontSee('data-ref="mpn"', false);
    }

    public function test_no_hardcoded_shop_name_in_view(): void
    {
        $view = (string) file_get_contents(resource_path('views/livewire/product-page.blade.php'));

        $this->assertStringNotContainsStringIgnoringCase('weklo', $view);
    }
}
