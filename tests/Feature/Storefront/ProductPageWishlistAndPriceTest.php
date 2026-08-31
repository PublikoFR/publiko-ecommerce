<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Product;
use Tests\TestCase;

class ProductPageWishlistAndPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function getProductWithUrl(): Product
    {
        $product = Product::query()->has('urls')->with('defaultUrl')->first();
        $this->assertNotNull($product, 'Seeded products with a URL are required.');

        return $product;
    }

    public function test_wishlist_button_hidden_when_unauthenticated(): void
    {
        $product = $this->getProductWithUrl();

        $this->get('/produits/'.$product->defaultUrl->slug)
            ->assertOk()
            ->assertDontSee('Ajouter à une liste');
    }

    public function test_wishlist_button_visible_on_product_page_when_authenticated(): void
    {
        $product = $this->getProductWithUrl();

        $this->actingAs(User::factory()->create())
            ->get('/produits/'.$product->defaultUrl->slug)
            ->assertOk()
            ->assertSee('Ajouter à une liste');
    }
}
