<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
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

        /** @var Staff $admin */
        $admin = Staff::query()->first();
        $this->assertNotNull($admin, 'Un Staff admin seedé est requis.');
        $this->actingAs($admin, 'staff');
    }

    public function test_saves_port_mode_and_supplier_on_product(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product, 'Un produit seedé est requis.');

        $supplier = Supplier::query()->create([
            'name' => 'Fournisseur Test',
            'bl_neutre' => false,
        ]);

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->call('setPortMode', 'quote')
            ->set('francoEligible', false)
            ->set('transportPriceEuros', null)
            ->set('supplierId', $supplier->id)
            ->call('save');

        $product->refresh();

        $this->assertSame('quote', $product->pko_port_mode);
        $this->assertFalse((bool) $product->pko_franco_eligible);
        $this->assertNull($product->pko_transport_price_cents);
        $this->assertSame($supplier->id, (int) $product->pko_supplier_id);
    }

    public function test_saves_transport_price_for_mode_flat(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->call('setPortMode', 'flat')
            ->set('transportPriceEuros', '45.00')
            ->call('save');

        $product->refresh();

        $this->assertSame('flat', $product->pko_port_mode);
        $this->assertSame(4500, (int) $product->pko_transport_price_cents);
    }

    public function test_clearing_transport_price_resets_it_to_null(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        $product->forceFill([
            'pko_port_mode' => 'flat',
            'pko_transport_price_cents' => 4500,
        ])->save();

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->call('setPortMode', 'flat')
            ->set('transportPriceEuros', null)
            ->call('save');

        $product->refresh();

        $this->assertNull($product->pko_transport_price_cents);
    }

    public function test_transport_price_not_saved_for_non_flat_modes(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->call('setPortMode', 'standard')
            ->set('transportPriceEuros', '45.00')
            ->call('save');

        $product->refresh();

        $this->assertSame('standard', $product->pko_port_mode);
        $this->assertNull($product->pko_transport_price_cents, 'Prix transport non enregistré hors mode flat');
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

        $product->forceFill([
            'pko_port_mode' => 'inherit',
            'pko_franco_eligible' => true,
            'pko_transport_price_cents' => null,
            'pko_supplier_id' => $supplier->id,
        ])->save();

        $component = Livewire::test(EditProductUnified::class, ['record' => $product->id]);

        $component
            ->assertSet('portMode', 'inherit')
            ->assertSet('francoEligible', true)
            ->assertSet('transportPriceEuros', null)
            ->assertSet('supplierId', $supplier->id);
    }

    public function test_set_port_mode_derives_franco_eligible(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        $component = Livewire::test(EditProductUnified::class, ['record' => $product->id]);

        $component->call('setPortMode', 'standard');
        $component->assertSet('francoEligible', true);

        $component->call('setPortMode', 'free');
        $component->assertSet('francoEligible', false);

        $component->call('setPortMode', 'quote');
        $component->assertSet('francoEligible', false);

        $component->call('setPortMode', 'flat');
        $component->assertSet('francoEligible', false);
    }
}
