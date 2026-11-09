<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\TreeManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\CatalogFeatures\Models\FeatureValue;
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

    /**
     * L'export PrestaShop a une racine `categories` (et non `tree`). Avant, ce
     * payload tombait dans le parseur de map plate `flatMapToTree()` et levait
     * « Array to string conversion » (500). Il doit être reconnu tel quel, en
     * conservant l'arborescence.
     */
    public function test_import_accepts_prestashop_categories_root(): void
    {
        $groupId = CollectionGroup::query()->orderBy('id')->value('id');

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();

        $payload = ['categories' => [[
            'name' => 'Portes',
            'img_src' => '',
            'children' => [[
                'name' => 'Portes blindées',
                'img_src' => '',
                'children' => [],
            ]],
        ]]];

        $instance->importCollectionsPayload(
            ['tree' => $this->invokeResolvePayload($instance, $payload)],
            'append',
        );

        $names = LunarCollection::query()
            ->where('collection_group_id', $groupId)
            ->get()
            ->map(fn (LunarCollection $c): string => (string) $c->translateAttribute('name', 'fr'));

        $this->assertContains('Portes', $names);
        $this->assertContains('Portes blindées', $names);

        $parent = LunarCollection::query()
            ->where('collection_group_id', $groupId)
            ->get()
            ->first(fn (LunarCollection $c): bool => $c->translateAttribute('name', 'fr') === 'Portes');
        $child = LunarCollection::query()
            ->where('collection_group_id', $groupId)
            ->get()
            ->first(fn (LunarCollection $c): bool => $c->translateAttribute('name', 'fr') === 'Portes blindées');

        $this->assertSame((int) $parent->id, (int) $child->parent_id, 'La hiérarchie doit être préservée.');
    }

    /**
     * Un `img_src` vide ne doit déclencher aucun import média.
     */
    public function test_blank_img_src_imports_no_media(): void
    {
        $groupId = CollectionGroup::query()->orderBy('id')->value('id');

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();

        $stats = $instance->importCollectionsPayload([
            'tree' => [['name' => 'Sans visuel', 'img_src' => '', 'children' => []]],
        ], 'append');

        $this->assertSame(['imported' => 0, 'skipped' => 0, 'errors' => 0], $stats);
    }

    /**
     * Aller-retour export → import du statut : un parent désactivé (et ses
     * enfants, désactivés en cascade) et un nœud en navigation par enfants
     * doivent ressortir identiques après réimport.
     */
    public function test_export_import_round_trip_preserves_status(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $groupId = CollectionGroup::query()->orderBy('id')->value('id');
        LunarCollection::query()->where('collection_group_id', $groupId)->get()
            ->each(fn (LunarCollection $c) => $c->delete());

        $parent = $this->makeCollection($groupId, 'Parent off');
        $parent->pko_enabled = false;
        $parent->pko_browse_children = true;
        $parent->saveAsRoot();

        $child = $this->makeCollection($groupId, 'Enfant off');
        $child->pko_enabled = false;
        $child->appendToNode($parent)->save();

        $active = $this->makeCollection($groupId, 'Actif');
        $active->saveAsRoot();

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();
        $payload = $instance->serializeCollectionsTree();

        $exportedParent = collect($payload['tree'])->firstWhere('name', 'Parent off');
        $this->assertFalse($exportedParent['enabled']);
        $this->assertTrue($exportedParent['browse_children']);
        $this->assertFalse($exportedParent['children'][0]['enabled']);
        $this->assertTrue(collect($payload['tree'])->firstWhere('name', 'Actif')['enabled']);

        // Passage par JSON pour coller au flux réel (fichier téléchargé).
        $decoded = json_decode((string) json_encode($payload), true);
        $instance->importCollectionsPayload($decoded, 'replace');

        $byName = LunarCollection::query()
            ->where('collection_group_id', $groupId)
            ->get()
            ->keyBy(fn (LunarCollection $c): string => (string) $c->translateAttribute('name', 'fr'));

        $this->assertCount(3, $byName);
        $this->assertFalse((bool) $byName['Parent off']->pko_enabled);
        $this->assertTrue((bool) $byName['Parent off']->pko_browse_children);
        $this->assertFalse((bool) $byName['Enfant off']->pko_enabled);
        $this->assertFalse((bool) $byName['Enfant off']->pko_browse_children);
        $this->assertSame((int) $byName['Parent off']->id, (int) $byName['Enfant off']->parent_id);
        $this->assertTrue((bool) $byName['Actif']->pko_enabled);
    }

    /**
     * JSON PrestaShop / anciens exports : sans `enabled` ni `browse_children`,
     * la catégorie est importée active, sans navigation par enfants.
     */
    public function test_import_without_status_fields_defaults_to_enabled(): void
    {
        $groupId = CollectionGroup::query()->orderBy('id')->value('id');

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();
        $instance->importCollectionsPayload([
            'tree' => [['name' => 'Sans statut', 'children' => [['name' => 'Enfant sans statut']]]],
        ], 'append');

        $imported = LunarCollection::query()
            ->where('collection_group_id', $groupId)
            ->get()
            ->filter(fn (LunarCollection $c): bool => in_array(
                $c->translateAttribute('name', 'fr'),
                ['Sans statut', 'Enfant sans statut'],
                true,
            ));

        $this->assertCount(2, $imported);
        foreach ($imported as $c) {
            $this->assertTrue((bool) $c->fresh()->pko_enabled);
            $this->assertFalse((bool) $c->fresh()->pko_browse_children);
        }
    }

    /**
     * Une catégorie importée depuis un ancien site garde son `source_url` :
     * l'export doit malgré tout émettre en `img_src` l'URL du média sur
     * l'instance courante, l'URL d'origine n'étant qu'informative.
     */
    public function test_export_img_src_is_local_url_even_with_source_url(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        Storage::fake('public');

        $groupId = CollectionGroup::query()->orderBy('id')->value('id');
        $c = $this->makeCollection($groupId, 'Avec visuel');
        $c->saveAsRoot();

        $oldUrl = 'https://ancien-site.example/img/c/12.jpg';
        $media = $c->addMedia(UploadedFile::fake()->image('visuel.jpg', 20, 20))
            ->withCustomProperties(['primary' => true, 'source_url' => $oldUrl])
            ->toMediaCollection('images');

        $component = Livewire::test(TreeManager::class);
        /** @var TreeManager $instance */
        $instance = $component->instance();
        $found = collect($instance->serializeCollectionsTree()['tree'])->firstWhere('name', 'Avec visuel');

        $this->assertSame($media->getFullUrl(), $found['img_src']);
        $this->assertNotSame($oldUrl, $found['img_src']);
        $this->assertSame($oldUrl, $found['img_source_url']);
        Http::assertNothingSent();
    }

    /**
     * @param  array<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function invokeResolvePayload(TreeManager $instance, array $payload): array
    {
        $method = new \ReflectionMethod($instance, 'resolveCollectionsTreePayload');
        $method->setAccessible(true);

        return $method->invoke($instance, $payload);
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
