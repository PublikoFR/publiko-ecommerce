<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Models\Product;
use Pko\Storefront\Livewire\SearchAutocomplete;
use Tests\TestCase;

class SearchAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_search_by_name_does_not_error_and_finds_product(): void
    {
        /** @var Product $product */
        $product = Product::query()->storefrontVisible()->with('variants')->first();
        $this->assertNotNull($product, 'Un produit visible storefront est requis (seed).');

        $name = (string) $product->translateAttribute('name');
        $term = mb_substr($name, 0, 6);

        Livewire::test(SearchAutocomplete::class)
            ->set('term', $term)
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));
    }

    public function test_search_by_sku_finds_product(): void
    {
        /** @var Product $product */
        $product = Product::query()
            ->storefrontVisible()
            ->whereHas('variants', fn ($q) => $q->whereNotNull('sku'))
            ->with('variants')
            ->first();
        $this->assertNotNull($product);

        $sku = (string) $product->variants->firstWhere('sku', '!=', null)?->sku;
        $this->assertNotSame('', $sku);

        Livewire::test(SearchAutocomplete::class)
            ->set('term', $sku)
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));
    }
}
