<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Pko\StorefrontCms\Http\Controllers\BrandController;
use Pko\StorefrontCms\Models\BrandPage;
use Tests\TestCase;

/**
 * Page publique /marques + entrée « Nos marques » du menu latéral.
 */
class BrandsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_lists_only_brands_with_published_products(): void
    {
        $withPublished = Brand::create(['name' => 'Zeta Publiee']);
        $this->publishedProduct()->update(['brand_id' => $withPublished->id]);

        $draftOnly = Brand::create(['name' => 'Alpha Brouillon']);
        Product::factory()->create(['status' => 'draft', 'brand_id' => $draftOnly->id]);

        $empty = Brand::create(['name' => 'Beta Vide']);

        $response = $this->get('/marques')->assertOk();

        $ids = $response->viewData('items')->pluck('brand.id');
        $this->assertTrue($ids->contains($withPublished->id));
        $this->assertFalse($ids->contains($draftOnly->id));
        $this->assertFalse($ids->contains($empty->id));

        $response->assertSee('Zeta Publiee')
            ->assertDontSee('Alpha Brouillon')
            ->assertDontSee('Beta Vide');
    }

    public function test_brands_are_sorted_alphabetically(): void
    {
        $products = Product::query()->storefrontSearchable()->limit(2)->get();
        $this->assertCount(2, $products);

        $b = Brand::create(['name' => 'Écrou Zzz']);
        $a = Brand::create(['name' => 'abeille Aaa']);
        $products[0]->update(['brand_id' => $b->id]);
        $products[1]->update(['brand_id' => $a->id]);

        $names = $this->get('/marques')->viewData('items')->pluck('brand.name')->values();

        $this->assertLessThan($names->search('Écrou Zzz'), $names->search('abeille Aaa'));
        $sorted = $names->sortBy(fn ($n) => mb_strtolower(Str::ascii($n)), SORT_NATURAL)->values();
        $this->assertSame($sorted->all(), $names->all());
    }

    public function test_link_targets_brand_page_or_filtered_search(): void
    {
        $withPage = Brand::create(['name' => 'Marque Avec Page']);
        $withoutPage = Brand::create(['name' => 'Marque Sans Page']);
        $products = Product::query()->storefrontSearchable()->limit(2)->get();
        $products[0]->update(['brand_id' => $withPage->id]);
        $products[1]->update(['brand_id' => $withoutPage->id]);

        BrandPage::create(['brand_id' => $withPage->id, 'content' => [['type' => 'text', 'data' => ['content' => 'x']]]]);
        // Page builder vide → pas de vraie page marque.
        BrandPage::create(['brand_id' => $withoutPage->id, 'content' => []]);

        $items = $this->get('/marques')->viewData('items')->keyBy('brand.id');

        $this->assertSame(route('brand.view', 'marque-avec-page'), $items[$withPage->id]['href']);
        $this->assertSame(route('search.view', ['b' => [$withoutPage->id => 1]]), $items[$withoutPage->id]['href']);

        $this->get($items[$withPage->id]['href'])->assertOk()->assertSee('Marque Avec Page');

        // Le repli recherche filtre bien sur la marque.
        $this->get($items[$withoutPage->id]['href'])
            ->assertOk()
            ->assertSee((string) $products[1]->translateAttribute('name'))
            ->assertDontSee((string) $products[0]->translateAttribute('name'));
    }

    public function test_alphabetical_index_above_threshold(): void
    {
        $items = collect(range(1, BrandController::ALPHA_INDEX_THRESHOLD + 1))->map(function (int $i) {
            $brand = new Brand(['name' => ($i % 2 ? 'Alpha ' : '3M ').$i]);
            $brand->id = 100000 + $i;

            return ['brand' => $brand, 'href' => '/x', 'logo' => '', 'letter' => $i % 2 ? 'A' : '#'];
        });

        $html = view('storefront-cms::brands.index', [
            'items' => $items,
            'groups' => $items->groupBy('letter'),
        ])->render();

        $this->assertStringContainsString('href="#marques-A"', $html);
        $this->assertStringContainsString('id="marques-autres"', $html);
    }

    public function test_lateral_menu_has_brands_entry(): void
    {
        $this->get('/marques')
            ->assertOk()
            ->assertSee('data-lateral-brands-link', false)
            ->assertSee('href="'.route('brands.index').'"', false)
            ->assertSee('Nos marques');

        $this->assertNotContains(
            route('brands.index'),
            array_column(config('storefront.nav.secondary', []), 'href'),
        );
    }

    private function publishedProduct(): Product
    {
        $product = Product::query()->storefrontSearchable()->first();
        $this->assertNotNull($product, 'Un produit publié avec URL est requis (seed).');

        return $product;
    }
}
