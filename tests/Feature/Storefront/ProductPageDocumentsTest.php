<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lunar\Models\Product;
use Pko\ProductDocuments\Models\DocumentCategory;
use Pko\ProductDocuments\Models\ProductDocument;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ProductPageDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getProductWithUrl(): Product
    {
        $product = Product::query()->has('urls')->with('defaultUrl')->first();
        $this->assertNotNull($product, 'Seeded products with a URL are required.');

        return $product;
    }

    /**
     * @return array{DocumentCategory, Media, ProductDocument}
     */
    private function createDocumentFixture(Product $product): array
    {
        $category = DocumentCategory::create([
            'label' => 'Fiches techniques',
            'handle' => 'fiches-techniques',
            'sort_order' => 1,
        ]);

        $media = Media::create([
            'model_type' => $product->getMorphClass(),
            'model_id' => $product->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'documents',
            'name' => 'Fiche technique produit',
            'file_name' => 'fiche-technique.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 12345,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $doc = ProductDocument::create([
            'product_id' => $product->id,
            'media_id' => $media->id,
            'category_id' => $category->id,
            'sort_order' => 1,
        ]);

        return [$category, $media, $doc];
    }

    // ─── Régression : guard 'customers' inexistant ────────────────────────────

    public function test_product_page_does_not_crash_when_unauthenticated_and_has_documents(): void
    {
        // Régression : auth('customers') levait InvalidArgumentException côté invité.
        $product = $this->getProductWithUrl();
        $this->createDocumentFixture($product);

        $this->get('/produits/'.$product->defaultUrl->slug)
            ->assertOk();
    }

    public function test_documents_section_hidden_when_unauthenticated(): void
    {
        $product = $this->getProductWithUrl();
        $this->createDocumentFixture($product);

        $this->get('/produits/'.$product->defaultUrl->slug)
            ->assertOk()
            ->assertDontSee('Documents téléchargeables');
    }

    // ─── Rendu authentifié ────────────────────────────────────────────────────

    public function test_authenticated_user_sees_documents_section(): void
    {
        $product = $this->getProductWithUrl();
        [, $media] = $this->createDocumentFixture($product);

        $this->actingAs(User::factory()->create())
            ->get('/produits/'.$product->defaultUrl->slug)
            ->assertOk()
            ->assertSee('Documents téléchargeables')
            ->assertSee($media->name);
    }

    public function test_documents_grouped_by_category_label(): void
    {
        $product = $this->getProductWithUrl();
        [$category] = $this->createDocumentFixture($product);

        $this->actingAs(User::factory()->create())
            ->get('/produits/'.$product->defaultUrl->slug)
            ->assertOk()
            ->assertSee($category->label);
    }

    public function test_document_without_category_falls_back_to_default_label(): void
    {
        $product = $this->getProductWithUrl();

        $media = Media::create([
            'model_type' => $product->getMorphClass(),
            'model_id' => $product->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'documents',
            'name' => 'Plan sans catégorie',
            'file_name' => 'plan.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 9999,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        ProductDocument::create([
            'product_id' => $product->id,
            'media_id' => $media->id,
            'category_id' => null,
            'sort_order' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/produits/'.$product->defaultUrl->slug)
            ->assertOk()
            ->assertSee('Documents');
    }
}
