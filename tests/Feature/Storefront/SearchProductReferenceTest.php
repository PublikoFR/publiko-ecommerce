<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\SearchPage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\Storefront\Livewire\SearchAutocomplete;
use Tests\TestCase;

/**
 * Recherche par référence boutique composée ET par référence fabricant (MPN),
 * avec tolérance espaces/tirets côté requête.
 */
class SearchProductReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->product = Product::query()->storefrontSearchable()->whereHas('variants')->firstOrFail();
        $brand = Brand::create(['name' => 'Refmarque', 'pko_ref_prefix' => 'RFQ']);
        $this->product->brand_id = $brand->id;
        $this->product->save();

        /** @var ProductVariant $variant */
        $variant = $this->product->variants()->firstOrFail();
        $variant->sku = 'RFQ0991-77';
        $variant->mpn = '0991-77';
        $variant->save();
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function terms(): array
    {
        return [
            'référence composée' => ['RFQ0991-77'],
            'composée minuscules' => ['rfq0991-77'],
            'MPN' => ['0991-77'],
            'composée sans tiret' => ['RFQ099177'],
            'MPN avec espaces' => ['0991 77'],
        ];
    }

    /**
     * @dataProvider terms
     */
    public function test_autocomplete_finds_product(string $term): void
    {
        Livewire::test(SearchAutocomplete::class)
            ->set('term', $term)
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->contains('id', $this->product->id));
    }

    /**
     * @dataProvider terms
     */
    public function test_search_page_finds_product(string $term): void
    {
        Livewire::test(SearchPage::class)
            ->set('term', $term)
            ->assertOk()
            ->assertViewHas('products', fn ($products) => collect($products->items())->contains('id', $this->product->id));
    }

    public function test_stored_references_are_never_altered(): void
    {
        Livewire::test(SearchAutocomplete::class)->set('term', 'RFQ 0991 77');

        $variant = $this->product->variants()->firstOrFail();
        $this->assertSame('RFQ0991-77', $variant->sku);
        $this->assertSame('0991-77', $variant->mpn);
    }
}
