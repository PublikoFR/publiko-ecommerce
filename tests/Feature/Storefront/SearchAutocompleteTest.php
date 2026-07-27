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

    public function test_search_finds_published_product_without_any_collection(): void
    {
        // Produit publié + URL + rattaché à au moins une catégorie…
        /** @var Product $product */
        $product = Product::query()
            ->storefrontSearchable()
            ->whereHas('collections')
            ->with('variants')
            ->first();
        $this->assertNotNull($product, 'Un produit recherchable et catégorisé est requis (seed).');

        // …dont on retire toute catégorie → il n'est plus storefrontVisible.
        $product->collections()->detach();
        $this->assertSame(
            0,
            Product::query()->storefrontVisible()->whereKey($product->id)->count(),
            'Sans catégorie, le produit ne doit plus être storefrontVisible.'
        );

        // Il reste néanmoins trouvable en recherche (storefrontSearchable).
        $name = (string) $product->translateAttribute('name');
        $term = mb_substr($name, 0, 6);

        Livewire::test(SearchAutocomplete::class)
            ->set('term', $term)
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));
    }

    public function test_search_matches_product_description(): void
    {
        /** @var Product $product */
        $product = Product::query()
            ->storefrontSearchable()
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(lunar_products.attribute_data, "$.description.value")) IS NOT NULL')
            ->first();
        $this->assertNotNull($product, 'Un produit recherchable avec description est requis (seed).');

        $name = mb_strtolower(strip_tags((string) $product->translateAttribute('name')));
        $nameWords = preg_split('/\s+/', $name) ?: [];
        $desc = mb_strtolower(strip_tags((string) $product->translateAttribute('description')));

        // Un mot présent dans la description mais absent du nom → isole le match description.
        $word = collect(preg_split('/[^a-zà-ÿ0-9]+/u', $desc) ?: [])
            ->first(fn (string $w): bool => mb_strlen($w) >= 4 && ! in_array($w, $nameWords, true));
        $this->assertNotNull($word, 'La description doit contenir un mot distinctif.');

        $found = Product::query()
            ->storefrontSearchable()
            ->storefrontSearchMatch($word)
            ->whereKey($product->id)
            ->exists();

        $this->assertTrue($found, "La recherche par un mot de la description doit trouver le produit (mot: {$word}).");
    }
}
