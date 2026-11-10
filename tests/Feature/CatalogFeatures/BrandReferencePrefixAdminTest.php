<?php

declare(strict_types=1);

namespace Tests\Feature\CatalogFeatures;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\BrandResource\Pages\ListBrands;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Brand;
use Pko\StorefrontCms\Filament\Pages\ManageBrandContent;
use Tests\TestCase;

/**
 * Champ « Préfixe de référence » de la fiche marque admin.
 */
class BrandReferencePrefixAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $admin = Staff::query()->first();
        $this->assertNotNull($admin, 'Un Staff admin seedé est requis.');
        $this->actingAs($admin, 'staff');
    }

    public function test_collision_is_refused_with_suggestions(): void
    {
        Brand::create(['name' => 'Somfy Test', 'pko_ref_prefix' => 'SOZ']);
        $brand = Brand::create(['name' => 'Sozo']);

        Livewire::test(ManageBrandContent::class, ['record' => $brand->getRouteKey()])
            ->fillForm(['pko_ref_prefix' => 'soz'])
            ->call('save')
            ->assertHasFormErrors(['pko_ref_prefix']);

        $this->assertNull($brand->refresh()->pko_ref_prefix);
    }

    public function test_valid_prefix_is_saved_uppercase(): void
    {
        $brand = Brand::create(['name' => 'Sozo']);

        Livewire::test(ManageBrandContent::class, ['record' => $brand->getRouteKey()])
            ->fillForm(['pko_ref_prefix' => ' szo2 ', 'slug' => 'sozo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('SZO2', $brand->refresh()->pko_ref_prefix);
    }

    public function test_invalid_format_is_refused(): void
    {
        $brand = Brand::create(['name' => 'Sozo']);

        Livewire::test(ManageBrandContent::class, ['record' => $brand->getRouteKey()])
            ->fillForm(['pko_ref_prefix' => 'S-Z'])
            ->call('save')
            ->assertHasFormErrors(['pko_ref_prefix']);
    }

    public function test_existing_prefix_does_not_collide_with_itself(): void
    {
        $brand = Brand::create(['name' => 'Sozo', 'pko_ref_prefix' => 'SOZ']);

        Livewire::test(ManageBrandContent::class, ['record' => $brand->getRouteKey()])
            ->fillForm(['slug' => 'sozo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('SOZ', $brand->refresh()->pko_ref_prefix);
    }

    public function test_create_without_prefix_gets_default_when_free(): void
    {
        Livewire::test(ListBrands::class)
            ->callAction('create', ['name' => 'Qwertz Motor'])
            ->assertHasNoActionErrors();

        $this->assertSame('QWE', Brand::where('name', 'Qwertz Motor')->firstOrFail()->pko_ref_prefix);
    }
}
