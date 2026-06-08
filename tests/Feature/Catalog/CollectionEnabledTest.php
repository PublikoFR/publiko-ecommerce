<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Filament\Pages\TreeManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Product;
use Tests\TestCase;

class CollectionEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->groupId = (int) CollectionGroup::query()->orderBy('id')->value('id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeCollection(string $name): LunarCollection
    {
        return new LunarCollection([
            'collection_group_id' => $this->groupId,
            'type' => 'static',
            'sort' => 'custom',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['fr' => new Text($name)])),
            ],
        ]);
    }

    // ─── Toggle persists ─────────────────────────────────────────────────────

    public function test_toggle_disables_collection(): void
    {
        $col = $this->makeCollection('Root');
        $col->saveAsRoot();

        $this->assertTrue((bool) $col->fresh()->pko_enabled);

        Livewire::test(TreeManager::class)
            ->call('toggleCollectionEnabled', $col->id);

        $this->assertFalse((bool) $col->fresh()->pko_enabled);
    }

    public function test_toggle_re_enables_collection(): void
    {
        $col = $this->makeCollection('Root');
        $col->saveAsRoot();
        $col->update(['pko_enabled' => false]);

        Livewire::test(TreeManager::class)
            ->call('toggleCollectionEnabled', $col->id);

        $this->assertTrue((bool) $col->fresh()->pko_enabled);
    }

    // ─── Cascade on disable ───────────────────────────────────────────────────

    public function test_disable_cascades_to_all_descendants(): void
    {
        $parent = $this->makeCollection('Parent');
        $parent->saveAsRoot();

        $child = $this->makeCollection('Child');
        $child->appendToNode($parent)->save();

        $grandchild = $this->makeCollection('Grandchild');
        $grandchild->appendToNode($child)->save();

        Livewire::test(TreeManager::class)
            ->call('toggleCollectionEnabled', $parent->id);

        $this->assertFalse((bool) $child->fresh()->pko_enabled);
        $this->assertFalse((bool) $grandchild->fresh()->pko_enabled);
    }

    public function test_re_enable_does_not_cascade_to_children(): void
    {
        $parent = $this->makeCollection('Parent');
        $parent->saveAsRoot();

        $child = $this->makeCollection('Child');
        $child->appendToNode($parent)->save();
        $child->update(['pko_enabled' => false]);

        $parent->update(['pko_enabled' => false]);

        // Re-enable parent only
        Livewire::test(TreeManager::class)
            ->call('toggleCollectionEnabled', $parent->id);

        $this->assertTrue((bool) $parent->fresh()->pko_enabled);
        $this->assertFalse((bool) $child->fresh()->pko_enabled, 'Child must stay disabled when parent is re-enabled.');
    }

    // ─── navVisible scope ─────────────────────────────────────────────────────

    public function test_nav_visible_excludes_disabled_collection(): void
    {
        $col = $this->makeCollection('Hidden');
        $col->saveAsRoot();
        $col->update(['pko_enabled' => false]);

        $visible = LunarCollection::query()->navVisible()->pluck('id');

        $this->assertNotContains($col->id, $visible);
    }

    public function test_nav_visible_excludes_child_of_disabled_ancestor(): void
    {
        $parent = $this->makeCollection('Parent');
        $parent->saveAsRoot();
        $parent->update(['pko_enabled' => false]);

        $child = $this->makeCollection('Child');
        $child->appendToNode($parent->refresh())->save();
        // Child is enabled but parent is disabled
        $child->update(['pko_enabled' => true]);
        $child->refresh();

        $visible = LunarCollection::query()->navVisible()->pluck('id');

        $this->assertNotContains($child->id, $visible, 'Child with disabled ancestor must not appear in navVisible.');
    }

    public function test_nav_visible_includes_enabled_collection(): void
    {
        $col = $this->makeCollection('Visible');
        $col->saveAsRoot();

        $visible = LunarCollection::query()->navVisible()->pluck('id');

        $this->assertContains($col->id, $visible);
    }

    // ─── storefrontVisible scope ──────────────────────────────────────────────

    public function test_storefront_visible_excludes_product_with_only_disabled_collection(): void
    {
        $col = $this->makeCollection('OnlyCat');
        $col->saveAsRoot();
        $col->update(['pko_enabled' => false]);

        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product, 'Seeded products are required.');

        // Attach product exclusively to the disabled collection
        $product->collections()->sync([$col->id]);

        $visible = Product::query()->storefrontVisible()->pluck('id');

        $this->assertNotContains($product->id, $visible);
    }

    public function test_storefront_visible_includes_product_with_enabled_collection(): void
    {
        $col = $this->makeCollection('EnabledCat');
        $col->saveAsRoot();

        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product, 'Seeded products are required.');
        $product->collections()->sync([$col->id]);

        $visible = Product::query()->storefrontVisible()->pluck('id');

        $this->assertContains($product->id, $visible);
    }

    // ─── TreeManager computed data ─────────────────────────────────────────────

    public function test_tree_includes_pko_enabled_field(): void
    {
        $col = $this->makeCollection('Traceable');
        $col->saveAsRoot();
        $col->update(['pko_enabled' => false]);

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();
        $tree = $instance->collectionsTree;

        $found = collect($tree)->firstWhere('id', $col->id);
        $this->assertNotNull($found);
        $this->assertFalse($found['pko_enabled']);
    }
}
