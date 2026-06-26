<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource\Pages\CreateShippingSurcharge;
use Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource\Pages\ListShippingSurcharges;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Tests\TestCase;

class ShippingSurchargeResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        /** @var User $admin */
        $admin = User::query()->first();
        $this->assertNotNull($admin, 'Un utilisateur admin seedé est requis.');
        $this->actingAs($admin);
    }

    public function test_list_page_renders(): void
    {
        Livewire::test(ListShippingSurcharges::class)
            ->assertSuccessful();
    }

    public function test_create_page_renders(): void
    {
        Livewire::test(CreateShippingSurcharge::class)
            ->assertSuccessful();
    }

    public function test_surcharges_seeded(): void
    {
        $this->assertDatabaseHas('pko_shipping_surcharges', [
            'code' => 'corse',
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('pko_shipping_surcharges', [
            'code' => 'transport_specifique',
            'mode' => 'quote',
        ]);

        $this->assertSame(9, ShippingSurcharge::query()->count());
    }
}
