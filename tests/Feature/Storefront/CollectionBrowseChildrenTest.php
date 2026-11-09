<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\CollectionPage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
 * Navigation en cascade : toute catégorie ayant au moins une sous-catégorie
 * visible affiche la grille de ses sous-catégories (avec image), sans produit,
 * sans tri ni pagination, sans sidebar de filtres — quel que soit
 * `pko_browse_children` et quels que soient les filtres de l'URL. Seules les
 * feuilles listent des produits et proposent des filtres.
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

    public function test_unflagged_parent_shows_cards_and_no_product(): void
    {
        [$parent, $child] = $this->makeBranch(browseChildren: false);

        $product = $this->seededProduct();
        // Produit rattaché au parent ET à l'enfant : en mode cartes il ne doit
        // apparaître nulle part.
        $parent->products()->attach($product->id);
        $child->products()->attach($product->id);

        Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)])
            ->assertSet('showsChildCards', true)
            ->assertViewHas('products', null)
            ->assertSee('Feuille')
            ->assertDontSee((string) $product->translateAttribute('name'))
            ->assertDontSee('Trier :');
    }

    public function test_cards_mode_has_no_filter_sidebar(): void
    {
        [$parent] = $this->makeBranch(browseChildren: false);

        Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)])
            ->assertDontSee('<aside', false)
            ->assertDontSee('wire:click="toggleBrand', false)
            ->assertDontSee('wire:click="toggleValue', false)
            ->assertViewHas('brands', fn ($brands) => $brands->isEmpty())
            ->assertViewHas('families', fn ($families) => $families->isEmpty());
    }

    public function test_url_filter_on_parent_shows_no_product(): void
    {
        [$parent, $child] = $this->makeBranch();

        $product = $this->seededProduct();
        $child->products()->attach($product->id);
        $brandId = (int) ($product->brand_id ?? 1);

        $component = Livewire::withQueryParams([
            'b' => [$brandId => true],
            'f' => [1 => [1 => true]],
            'sort' => 'price-asc',
        ])->test(CollectionPage::class, ['slug' => $this->slug($parent)]);

        $component
            ->assertSet('showsChildCards', true)
            ->assertViewHas('products', null)
            ->assertDontSee((string) $product->translateAttribute('name'))
            ->assertSet('selectedBrands', [])
            ->assertSet('selected', [])
            ->assertSet('sort', 'new');
    }

    public function test_leaf_keeps_products_and_filters(): void
    {
        [, $child] = $this->makeBranch();

        $product = $this->seededProduct();
        $child->products()->attach($product->id);

        // La feuille hérite du drapeau via la cascade, mais n'a pas d'enfant :
        // elle garde son listing produits et sa sidebar de filtres.
        $component = Livewire::test(CollectionPage::class, ['slug' => $this->slug($child)]);

        $this->assertFalse($component->instance()->showsChildCards);
        $this->assertGreaterThan(0, $component->instance()->products->total());
        $component
            ->assertSee((string) $product->translateAttribute('name'))
            ->assertSee('<aside', false)
            ->assertSee('Filtres')
            ->assertSee('Trier :');
    }

    public function test_child_image_is_rendered(): void
    {
        Storage::fake('public');

        [$parent, $child] = $this->makeBranch(browseChildren: false);
        $media = $child->addMedia(UploadedFile::fake()->image('visuel.jpg', 20, 20))
            ->toMediaCollection('images');

        Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)])
            ->assertSee(pko_media_url($media->fresh(), 'medium'), false)
            ->assertDontSee('data-testid="child-placeholder"', false);
    }

    public function test_child_without_image_gets_placeholder(): void
    {
        [$parent] = $this->makeBranch(browseChildren: false);

        Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)])
            ->assertSee('data-testid="child-placeholder"', false);
    }

    /**
     * Plus d'élargissement aux descendants : une feuille ne liste que ses
     * produits directs, jamais ceux d'une sous-catégorie masquée.
     */
    public function test_leaf_ignores_hidden_descendant_products(): void
    {
        [$parent, $child] = $this->makeBranch();

        // Le produit garde ses collections seedées visibles : sans la garde,
        // l'ancien élargissement _lft/_rgt le ferait remonter sur le parent.
        $child->products()->attach($this->seededProduct()->id);
        $child->pko_enabled = false;
        $child->save();

        // Le parent devient une feuille (son seul enfant est masqué).
        $component = Livewire::test(CollectionPage::class, ['slug' => $this->slug($parent)]);

        $this->assertFalse($component->instance()->showsChildCards);
        $this->assertSame(0, $component->instance()->products->total());
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
