<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\TreeManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Mde\CatalogFeatures\Models\FeatureFamily;
use Mde\CatalogFeatures\Models\FeatureValue;
use Tests\TestCase;

class TreeManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_mount_hydrates_collections_and_features(): void
    {
        $this->makeFeatureFixture();

        Livewire::test(TreeManager::class)
            ->assertSet('collectionGroupId', fn ($v) => $v !== null)
            ->assertSet(
                'collectionsTree',
                fn (array $tree): bool => count($tree) > 0 && isset($tree[0]['children']),
            )
            ->assertSet(
                'featureFamilies',
                fn (array $families): bool => collect($families)->contains(fn ($f) => $f['handle'] === 'connectivite'),
            );
    }

    public function test_move_collection_reparents_and_reorders(): void
    {
        $groupId = CollectionGroup::query()->orderBy('id')->value('id');

        $a = $this->makeCollection($groupId, 'Aaa');
        $a->saveAsRoot();
        $b = $this->makeCollection($groupId, 'Bbb');
        $b->saveAsRoot();

        Livewire::test(TreeManager::class)
            ->call('moveCollection', $b->id, $a->id, 0);

        $b->refresh();
        $this->assertSame($a->id, $b->parent_id);
    }

    public function test_move_feature_value_to_different_family_renumbers_both(): void
    {
        $src = FeatureFamily::create(['handle' => 'src', 'name' => 'Source', 'position' => 0]);
        $dst = FeatureFamily::create(['handle' => 'dst', 'name' => 'Destination', 'position' => 1]);

        $v1 = FeatureValue::create(['feature_family_id' => $src->id, 'handle' => 'v1', 'name' => 'V1', 'position' => 0]);
        $v2 = FeatureValue::create(['feature_family_id' => $src->id, 'handle' => 'v2', 'name' => 'V2', 'position' => 1]);
        $v3 = FeatureValue::create(['feature_family_id' => $dst->id, 'handle' => 'v3', 'name' => 'V3', 'position' => 0]);

        Livewire::test(TreeManager::class)
            ->call('moveFeatureValue', $v1->id, $dst->id, 0);

        $v1->refresh();
        $v2->refresh();
        $v3->refresh();

        $this->assertSame($dst->id, $v1->feature_family_id);
        $this->assertSame(0, $v1->position);
        $this->assertSame(1, $v3->position);
        $this->assertSame(0, $v2->position, 'Source family must be renumbered after the move.');
    }

    public function test_move_feature_family_renumbers_positions(): void
    {
        FeatureFamily::query()->delete();

        $a = FeatureFamily::create(['handle' => 'a', 'name' => 'A', 'position' => 0]);
        $b = FeatureFamily::create(['handle' => 'b', 'name' => 'B', 'position' => 1]);
        $c = FeatureFamily::create(['handle' => 'c', 'name' => 'C', 'position' => 2]);

        Livewire::test(TreeManager::class)
            ->call('moveFeatureFamily', $c->id, 0);

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_export_collections_payload_contains_seo_fields(): void
    {
        $groupId = CollectionGroup::query()->orderBy('id')->value('id');

        $c = $this->makeCollection($groupId, 'Root', [
            'meta_title' => 'SEO title',
            'meta_description' => 'SEO description',
        ]);
        $c->saveAsRoot();

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();
        $payload = $instance->serializeCollectionsTree();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('tree', $payload);
        $found = collect($payload['tree'])->firstWhere('name', 'Root');
        $this->assertNotNull($found);
        $this->assertSame('SEO title', $found['meta_title']);
        $this->assertSame('SEO description', $found['meta_description']);
    }

    public function test_import_features_round_trip(): void
    {
        FeatureFamily::query()->delete();

        $payload = [
            'version' => 1,
            'families' => [
                [
                    'handle' => 'connectivite',
                    'name' => 'Connectivité',
                    'multi_value' => true,
                    'searchable' => true,
                    'values' => [
                        ['handle' => 'bluetooth', 'name' => 'Bluetooth'],
                        ['handle' => 'wifi', 'name' => 'Wi-Fi'],
                    ],
                ],
            ],
        ];

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();
        $instance->importFeaturesPayload($payload);

        $family = FeatureFamily::where('handle', 'connectivite')->firstOrFail();
        $this->assertTrue((bool) $family->multi_value);
        $this->assertTrue((bool) $family->searchable);
        $this->assertSame(2, $family->values()->count());
        $this->assertSame(0, $family->values->firstWhere('handle', 'bluetooth')->position);
        $this->assertSame(1, $family->values->firstWhere('handle', 'wifi')->position);
    }

    public function test_create_collection_action_persists_seo_and_parenting(): void
    {
        $groupId = CollectionGroup::query()->orderBy('id')->value('id');
        $parent = $this->makeCollection($groupId, 'Parent');
        $parent->saveAsRoot();

        Livewire::test(TreeManager::class)
            ->mountAction('createCollectionAction', ['parent_id' => $parent->id])
            ->setActionData([
                'name' => 'Child',
                'description' => null,
                'meta_title' => 'Meta child',
                'meta_description' => null,
                'image' => null,
            ])
            ->callMountedAction();

        $child = LunarCollection::query()
            ->where('parent_id', $parent->id)
            ->get()
            ->last();

        $this->assertNotNull($child);
        $this->assertSame('Child', (string) $child->translateAttribute('name', 'fr'));
        $this->assertSame('Meta child', (string) $child->translateAttribute('meta_title', 'fr'));
    }

    private function makeCollection(int $groupId, string $name, array $extra = []): LunarCollection
    {
        $data = collect([
            'name' => new TranslatedText(collect(['fr' => new LunarText($name)])),
        ]);

        foreach ($extra as $key => $value) {
            $data->put($key, new TranslatedText(collect(['fr' => new LunarText($value)])));
        }

        return new LunarCollection([
            'collection_group_id' => $groupId,
            'type' => 'static',
            'sort' => 'custom',
            'attribute_data' => $data,
        ]);
    }

    private function makeFeatureFixture(): void
    {
        $family = FeatureFamily::firstOrCreate(
            ['handle' => 'connectivite'],
            ['name' => 'Connectivité', 'position' => 0, 'multi_value' => true, 'searchable' => false],
        );
        FeatureValue::firstOrCreate(
            ['feature_family_id' => $family->id, 'handle' => 'bluetooth'],
            ['name' => 'Bluetooth', 'position' => 0],
        );
    }
}
