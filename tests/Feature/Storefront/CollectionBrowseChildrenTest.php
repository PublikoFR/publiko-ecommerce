<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\CollectionPage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\Url;
use Tests\TestCase;

/**
 * Mode « page de listing de catégories » (`pko_browse_children`) : la catégorie
 * affiche ses sous-catégories en cartes au lieu de ses produits, et un filtre
 * actif rebascule en mode produits sur TOUTE la branche.
 */
class CollectionBrowseChildrenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_flagged_collection_with_children_shows_cards(): void
    {
        [$parent, $child] = $this->makeBranch();

        $component = Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)]);

        $this->assertTrue($component->instance()->showsChildCards);
        $this->assertTrue(
            $component->instance()->childCollections->contains('id', $child->id),
            'La sous-catégorie doit apparaître en carte.',
        );
    }

    public function test_leaf_falls_back_to_products(): void
    {
        [, $child] = $this->makeBranch();

        // La feuille hérite du drapeau via la cascade, mais n'a pas d'enfant :
        // elle doit retomber sur son listing produits.
        $component = Livewire::test(CollectionPage::class, ['slug' => $this->slug($child)]);

        $this->assertFalse($component->instance()->showsChildCards);
    }

    public function test_unflagged_collection_keeps_product_listing(): void
    {
        [$parent] = $this->makeBranch(browseChildren: false);

        $component = Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)]);

        $this->assertFalse($component->instance()->showsChildCards);
    }

    /**
     * Le cœur du mode : depuis un niveau intermédiaire, les produits ne sont
     * rattachés qu'aux feuilles. Sans l'élargissement nestedset, filtrer depuis
     * la racine ne renverrait rien.
     */
    public function test_branch_mode_includes_descendant_products(): void
    {
        [$parent, $child] = $this->makeBranch();

        $product = $this->seededProduct();
        $child->products()->attach($product->id);

        // Non marquée : seuls les produits directement rattachés comptent → 0.
        $parent->pko_browse_children = false;
        $parent->save();
        $direct = Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)])
            ->instance()->products->total();

        // Marquée : les produits des descendants remontent.
        $parent->pko_browse_children = true;
        $parent->save();
        $branche = Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)])
            ->instance()->products->total();

        $this->assertSame(0, $direct, 'Sans le mode branche, aucun produit direct sur le parent.');
        $this->assertGreaterThan(0, $branche, 'En mode branche, le produit du descendant doit remonter.');
    }

    public function test_breadcrumb_follows_hierarchy(): void
    {
        [$parent, $child] = $this->makeBranch();

        $items = Livewire::test(CollectionPage::class, ['slug' => $this->slug($child)])
            ->instance()->breadcrumbItems;

        $labels = array_column($items, 'label');

        $this->assertSame(['Aiguillage', 'Feuille'], $labels);
    }

    /**
     * @return array{0: LunarCollection, 1: LunarCollection}
     */
    private function makeBranch(bool $browseChildren = true): array
    {
        $groupId = LunarCollection::query()->value('collection_group_id')
            ?? CollectionGroup::query()->value('id');

        $parent = $this->makeCollection($groupId, 'Aiguillage');
        $parent->saveAsRoot();

        $child = $this->makeCollection($groupId, 'Feuille');
        $child->appendToNode($parent->refresh())->save();

        if ($browseChildren) {
            // Colonne non fillable côté Lunar : assignation directe.
            foreach ([$parent, $child] as $node) {
                $node->pko_browse_children = true;
                $node->save();
            }
        }

        LunarCollection::fixTree();

        return [$parent->refresh(), $child->refresh()];
    }

    private function makeCollection(int $groupId, string $name): LunarCollection
    {
        $collection = new LunarCollection([
            'collection_group_id' => $groupId,
            'type' => 'static',
            'sort' => 'custom',
            'attribute_data' => collect([
                'name' => new TranslatedText(collect(['fr' => new LunarText($name)])),
            ]),
        ]);

        return $collection;
    }

    private function slug(LunarCollection $collection): string
    {
        $url = $collection->urls()->first();

        if ($url === null) {
            $url = Url::create([
                'language_id' => Language::query()->value('id'),
                'element_type' => $collection->getMorphClass(),
                'element_id' => $collection->id,
                'slug' => 'branche-'.$collection->id,
                'default' => true,
            ]);
        }

        return (string) $url->slug;
    }

    private function seededProduct(): Product
    {
        $product = Product::query()->first();

        $this->assertNotNull($product, 'Le seeder doit fournir au moins un produit.');

        return $product;
    }
}
