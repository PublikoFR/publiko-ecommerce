<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Models\Product;
use Pko\ShippingCommon\Models\Supplier;
use Tests\TestCase;

class EditProductUnifiedShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_saves_logistics_fields_on_product(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product, 'Un produit seedé est requis.');

        $supplier = Supplier::query()->create([
            'name' => 'Fournisseur Test',
            'bl_neutre' => false,
        ]);

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->set('logisticsClass', 'B')
            ->set('francoEligible', false)
            ->set('transportPriceCents', null)
            ->set('quoteOnly', true)
            ->set('supplierId', $supplier->id)
            ->call('save');

        $product->refresh();

        $this->assertSame('B', $product->pko_logistics_class);
        $this->assertFalse((bool) $product->pko_franco_eligible);
        $this->assertNull($product->pko_transport_price_cents);
        $this->assertTrue((bool) $product->pko_quote_only);
        $this->assertSame($supplier->id, (int) $product->pko_supplier_id);
    }

    public function test_saves_transport_price_for_class_c(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->set('logisticsClass', 'C')
            ->set('transportPriceCents', 4500)
            ->call('save');

        $product->refresh();

        $this->assertSame('C', $product->pko_logistics_class);
        $this->assertSame(4500, (int) $product->pko_transport_price_cents);
    }

    public function test_hydrates_shipping_fields_from_product(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        $supplier = Supplier::query()->create([
            'name' => 'Fournisseur Hydration',
            'bl_neutre' => true,
        ]);

        $product->update([
            'pko_logistics_class' => 'A',
            'pko_franco_eligible' => true,
            'pko_transport_price_cents' => null,
            'pko_quote_only' => false,
            'pko_supplier_id' => $supplier->id,
        ]);

        $component = Livewire::test(EditProductUnified::class, ['record' => $product->id]);

        $component
            ->assertSet('logisticsClass', 'A')
            ->assertSet('francoEligible', true)
            ->assertSet('transportPriceCents', null)
            ->assertSet('quoteOnly', false)
            ->assertSet('supplierId', $supplier->id);
    }
}
