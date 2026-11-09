<?php

declare(strict_types=1);

namespace Tests\Feature\Ecotax;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class EditProductUnifiedEcotaxTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $admin = Staff::query()->first();
        $this->assertNotNull($admin, 'Un Staff admin seedé est requis.');
        $this->actingAs($admin, 'staff');

        $this->product = Product::query()->firstOrFail();
    }

    private function variant(): ProductVariant
    {
        return $this->product->variants()->firstOrFail();
    }

    public function test_loads_and_saves_ecotax_in_cents(): void
    {
        $variant = $this->variant();
        $variant->pko_ecotax_cents = 16;
        $variant->save();

        Livewire::test(EditProductUnified::class, ['record' => $this->product->id])
            ->assertSet('ecotax', '0.16')
            ->set('price', '186.68')
            ->set('ecotax', '0,19')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(19, (int) $this->variant()->pko_ecotax_cents);
    }

    public function test_blank_ecotax_is_saved_as_null(): void
    {
        $variant = $this->variant();
        $variant->pko_ecotax_cents = 16;
        $variant->save();

        Livewire::test(EditProductUnified::class, ['record' => $this->product->id])
            ->set('price', '100')
            ->set('ecotax', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->variant()->pko_ecotax_cents);
    }

    public function test_ecotax_must_be_positive_and_not_exceed_price(): void
    {
        Livewire::test(EditProductUnified::class, ['record' => $this->product->id])
            ->set('price', '10')
            ->set('ecotax', '10.01')
            ->call('save')
            ->assertHasErrors(['ecotax']);

        Livewire::test(EditProductUnified::class, ['record' => $this->product->id])
            ->set('price', '10')
            ->set('ecotax', '-1')
            ->call('save')
            ->assertHasErrors(['ecotax' => 'min']);

        $this->assertNull($this->variant()->pko_ecotax_cents);
    }
}
