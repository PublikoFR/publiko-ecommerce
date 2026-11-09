<?php

declare(strict_types=1);

namespace Tests\Feature\CatalogFeatures;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use App\Livewire\SearchPage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Models\ProductCompatibility;
use Pko\Storefront\Livewire\SearchAutocomplete;
use Tests\TestCase;

/**
 * Compatibilités documentées d'une pièce détachée : CRUD admin (éditeur
 * unifié), bloc de la fiche produit et recherche par référence d'équipement.
 */
class ProductCompatibilitiesTest extends TestCase
{
    use RefreshDatabase;

    private Product $part;

    private Product $other;

    private Brand $equipmentBrand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $products = Product::query()->storefrontSearchable()->with(['defaultUrl', 'variants'])->limit(2)->get();
        $this->assertCount(2, $products, 'Deux produits publiés avec URL sont requis (seed).');

        [$this->part, $this->other] = [$products[0], $products[1]];
        $this->equipmentBrand = Brand::create(['name' => 'Marque Équipement Test']);
    }

    private function actingAsAdmin(): void
    {
        $admin = Staff::query()->first();
        $this->assertNotNull($admin, 'Un Staff admin seedé est requis.');
        $this->actingAs($admin, 'staff');
    }

    private function compat(array $attrs = []): ProductCompatibility
    {
        return ProductCompatibility::create($attrs + [
            'product_id' => $this->part->id,
            'equipment_brand_id' => $this->equipmentBrand->id,
            'equipment_model' => 'Tondeuse T-500',
            'equipment_reference' => 'EQREF-9X42',
            'sort_order' => 0,
        ]);
    }

    private function visit(Product $product): TestResponse
    {
        return $this->get('/produits/'.$product->defaultUrl->slug)->assertOk();
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    public function test_admin_adds_edits_reorders_and_removes_compatibilities(): void
    {
        $this->actingAsAdmin();

        $page = Livewire::test(EditProductUnified::class, ['record' => $this->part->id])
            ->assertSet('compatibilities', [])
            ->call('addCompatibilityRow')
            ->set('compatibilities.0.equipment_brand_id', $this->equipmentBrand->id)
            ->set('compatibilities.0.equipment_model', 'Modèle A')
            ->set('compatibilities.0.equipment_reference', 'REF-A')
            ->call('pickCompatibleProduct', 0, $this->other->id)
            ->call('addCompatibilityRow')
            ->set('compatibilities.1.equipment_brand_label', 'Marque Hors Catalogue')
            ->set('compatibilities.1.equipment_reference', 'REF-B')
            ->call('addCompatibilityRow') // ligne vide : ignorée
            ->call('save')
            ->assertHasNoErrors();

        $rows = $this->part->compatibilities()->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['REF-A', 'REF-B'], $rows->pluck('equipment_reference')->all());
        $this->assertSame($this->other->id, $rows[0]->compatible_product_id);
        $this->assertSame($this->equipmentBrand->id, $rows[0]->equipment_brand_id);
        $this->assertNull($rows[1]->equipment_brand_id);
        $this->assertSame('Marque Hors Catalogue', $rows[1]->equipmentBrandName());
        // Les nouvelles lignes ont reçu leur id : pas de doublon au 2ᵉ save.
        $this->assertSame($rows->pluck('id')->all(), array_column($page->get('compatibilities'), 'id'));

        $firstId = $rows[0]->id;
        $page->set('compatibilities.0.equipment_model', 'Modèle A2')
            ->call('moveCompatibilityRow', 0, 1)
            ->call('save')
            ->assertHasNoErrors();

        $rows = $this->part->compatibilities()->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['REF-B', 'REF-A'], $rows->pluck('equipment_reference')->all());
        $this->assertSame($firstId, $rows[1]->id, 'L\'édition conserve l\'id de la ligne.');
        $this->assertSame('Modèle A2', $rows[1]->equipment_model);

        $page->call('removeCompatibilityRow', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$firstId], $this->part->compatibilities()->pluck('id')->all());
    }

    public function test_admin_loads_existing_rows_and_clears_free_label_when_brand_chosen(): void
    {
        $this->actingAsAdmin();
        $row = $this->compat(['equipment_brand_id' => null, 'equipment_brand_label' => 'Libre']);

        Livewire::test(EditProductUnified::class, ['record' => $this->part->id])
            ->assertSet('compatibilities.0.id', $row->id)
            ->assertSet('compatibilities.0.equipment_brand_label', 'Libre')
            ->set('compatibilities.0.equipment_brand_id', $this->equipmentBrand->id)
            ->call('save')
            ->assertHasNoErrors();

        $row->refresh();
        $this->assertSame($this->equipmentBrand->id, $row->equipment_brand_id);
        $this->assertNull($row->equipment_brand_label);
    }

    public function test_admin_rejects_unknown_compatible_product(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductUnified::class, ['record' => $this->part->id])
            ->call('addCompatibilityRow')
            ->set('compatibilities.0.equipment_reference', 'REF-X')
            ->set('compatibilities.0.compatible_product_id', 999999)
            ->call('save')
            ->assertHasErrors(['compatibilities.0.compatible_product_id']);

        $this->assertSame(0, $this->part->compatibilities()->count());
    }

    // ─── Fiche produit ────────────────────────────────────────────────────────

    public function test_block_hidden_without_compatibility(): void
    {
        $this->visit($this->part)
            ->assertDontSee('data-testid="product-compatibilities"', false)
            ->assertDontSee('Compatibilités documentées par le fabricant');
    }

    public function test_block_lists_brand_model_reference_and_link(): void
    {
        $this->compat(['compatible_product_id' => $this->other->id]);
        $this->compat([
            'equipment_brand_id' => null,
            'equipment_brand_label' => 'Fabricant Libre',
            'equipment_model' => null,
            'equipment_reference' => 'LIBRE-01',
            'sort_order' => 1,
        ]);

        $otherUrl = route('product.view', ['slug' => $this->other->defaultUrl->slug]);

        $this->visit($this->part)
            ->assertSee('data-testid="product-compatibilities"', false)
            ->assertSee('Compatibilités documentées par le fabricant')
            ->assertSeeInOrder(['Marque Équipement Test', 'Tondeuse T-500', 'EQREF-9X42', 'Fabricant Libre', 'LIBRE-01'])
            ->assertSee('href="'.$otherUrl.'"', false);
    }

    public function test_link_hidden_when_compatible_product_not_published(): void
    {
        $this->other->update(['status' => 'draft']);
        $this->compat(['compatible_product_id' => $this->other->id]);

        $otherUrl = route('product.view', ['slug' => $this->other->defaultUrl->slug]);

        $this->visit($this->part)
            ->assertSee('EQREF-9X42')
            ->assertDontSee('href="'.$otherUrl.'"', false);
    }

    // ─── Recherche ────────────────────────────────────────────────────────────

    public function test_search_by_equipment_reference_finds_part(): void
    {
        $this->compat();

        Livewire::test(SearchAutocomplete::class)
            ->set('term', 'eqref-9x')
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$this->part->id]);

        Livewire::test(SearchPage::class, ['term' => 'EQREF-9X42'])
            ->assertOk()
            ->assertViewHas('products', fn ($products) => collect($products->items())->pluck('id')->all() === [$this->part->id]);
    }

    public function test_search_by_equipment_model_finds_part(): void
    {
        $this->compat();

        $this->assertSame(
            [$this->part->id],
            Product::query()->storefrontSearchable()->storefrontSearchMatch('Tondeuse T-5')->pluck('id')->all(),
        );
    }
}
