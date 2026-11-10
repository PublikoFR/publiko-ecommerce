<?php

declare(strict_types=1);

namespace Tests\Feature\StorefrontCms;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Brand;
use Lunar\Models\Language;
use Pko\StorefrontCms\Filament\Pages\ManageBrandContent;
use Pko\StorefrontCms\Models\BrandPage;
use Tests\TestCase;

/**
 * Fiche marque unifiée : infos + URL + SEO + builder sur la route `edit`.
 */
class BrandUnifiedPageTest extends TestCase
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

    public function test_edit_route_serves_unified_page_with_three_tabs(): void
    {
        $brand = Brand::create(['name' => 'Acme Volets']);

        $this->get(BrandResource::getUrl('edit', ['record' => $brand]))
            ->assertOk()
            ->assertSeeLivewire(ManageBrandContent::class);

        $labels = collect(BrandResource::getRecordSubNavigation(
            Livewire::test(ManageBrandContent::class, ['record' => $brand->getKey()])->instance(),
        ))->map->getLabel()->values()->all();

        $this->assertSame(['Marque', 'Produits', 'Collections'], $labels);
    }

    public function test_save_updates_name_slug_and_seo(): void
    {
        $brand = Brand::create(['name' => 'Acme Volets']);

        Livewire::test(ManageBrandContent::class, ['record' => $brand->getKey()])
            ->fillForm([
                'name' => 'Acme Fermetures',
                'slug' => 'Acme Fermetures Pro',
                'seoTitle' => 'Titre SEO',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $brand->refresh();
        $this->assertSame('Acme Fermetures', $brand->name);
        $this->assertSame('acme-fermetures-pro', $brand->urls()->where('default', true)->value('slug'));
        $this->assertSame('Titre SEO', BrandPage::where('brand_id', $brand->id)->value('seo_title'));
    }

    public function test_slug_taken_by_another_brand_is_refused(): void
    {
        $other = Brand::create(['name' => 'Autre']);
        $other->urls()->create([
            'slug' => 'deja-prise',
            'language_id' => Language::getDefault()->id,
            'default' => true,
        ]);
        $brand = Brand::create(['name' => 'Acme Volets']);

        Livewire::test(ManageBrandContent::class, ['record' => $brand->getKey()])
            ->fillForm(['slug' => 'deja-prise'])
            ->call('save')
            ->assertHasFormErrors(['slug']);
    }

    public function test_builder_save_event_persists_form(): void
    {
        $brand = Brand::create(['name' => 'Acme Volets']);
        $component = Livewire::test(ManageBrandContent::class, ['record' => $brand->getKey()]);
        $brandPageId = (int) $component->get('brandPage')->getKey();

        $component
            ->fillForm(['name' => 'Acme Renommée', 'slug' => 'acme'])
            ->dispatch('page-builder-saved', modelClass: BrandPage::class, recordId: $brandPageId)
            ->assertHasNoFormErrors();

        $this->assertSame('Acme Renommée', $brand->refresh()->name);
    }
}
