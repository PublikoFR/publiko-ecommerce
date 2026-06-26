<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Pko\ShippingCommon\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use Pko\ShippingCommon\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use Tests\TestCase;

class SupplierResourceTest extends TestCase
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
        Livewire::test(ListSuppliers::class)
            ->assertSuccessful();
    }

    public function test_create_page_renders(): void
    {
        Livewire::test(CreateSupplier::class)
            ->assertSuccessful();
    }
}
