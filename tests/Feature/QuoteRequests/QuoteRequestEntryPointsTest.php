<?php

declare(strict_types=1);

namespace Tests\Feature\QuoteRequests;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\Url;
use Pko\StorefrontCms\Filament\Pages\StorefrontSettings;
use Pko\StorefrontCms\Models\Setting;
use Pko\StorefrontCms\Support\SparePartsCatalog;
use Tests\TestCase;

/**
 * Points d'entrée du formulaire de devis :
 * - bandeau « Un doute sur la pièce à commander ? » (mode `part_photo`) sur
 *   l'arborescence Pièces détachées : catégories, sous-catégories et fiches
 *   des produits rattachés ; absent ailleurs ;
 * - bouton « Demander un conseil ou un devis » (mode général) sur toutes les
 *   fiches, visiteur non connecté compris.
 */
class QuoteRequestEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    private const BANNER = 'data-testid="part-help-banner"';

    private const ADVICE = 'data-testid="quote-advice-button"';

    private LunarCollection $partsRoot;

    private LunarCollection $partsChild;

    private LunarCollection $otherRoot;

    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->groupId = (int) (LunarCollection::query()->value('collection_group_id')
            ?? CollectionGroup::query()->value('id'));

        $this->partsRoot = $this->makeRoot('Pièces détachées', SparePartsCatalog::DEFAULT_SLUG);
        $this->partsChild = $this->makeChild($this->partsRoot, 'Moteurs', 'pieces-moteurs');
        $this->otherRoot = $this->makeRoot('Automatismes', 'automatismes-test');

        LunarCollection::fixTree();
        $this->partsRoot->refresh();
        $this->partsChild->refresh();
        $this->otherRoot->refresh();
    }

    public function test_banner_on_spare_parts_root_collection(): void
    {
        $html = $this->get('/collections/pieces-detachees')->assertOk()->assertSee(self::BANNER, false)
            ->assertSee('Un doute sur la pièce à commander ?')
            ->assertSee('Envoyer une photo')
            ->getContent();

        $query = $this->bannerQuery($html);
        $this->assertSame('part_photo', $query['mode'] ?? null);
        $this->assertSame('pieces-detachees', $query['collection'] ?? null);
        $this->assertSame('/collections/pieces-detachees', $query['from'] ?? null);
    }

    public function test_banner_on_spare_parts_sub_collection_with_its_own_context(): void
    {
        $html = $this->get('/collections/pieces-moteurs')->assertOk()->assertSee(self::BANNER, false)->getContent();

        $query = $this->bannerQuery($html);
        $this->assertSame('part_photo', $query['mode'] ?? null);
        $this->assertSame('pieces-moteurs', $query['collection'] ?? null);
        $this->assertSame('/collections/pieces-moteurs', $query['from'] ?? null);
    }

    public function test_no_banner_outside_spare_parts_tree(): void
    {
        $this->get('/collections/automatismes-test')->assertOk()->assertDontSee(self::BANNER, false);
    }

    public function test_root_is_designated_by_setting(): void
    {
        Setting::set(SparePartsCatalog::SETTING_KEY, $this->otherRoot->id);

        $this->get('/collections/automatismes-test')->assertOk()->assertSee(self::BANNER, false);
        $this->get('/collections/pieces-detachees')->assertOk()->assertDontSee(self::BANNER, false);
        $this->get('/collections/pieces-moteurs')->assertOk()->assertDontSee(self::BANNER, false);
    }

    public function test_unknown_setting_falls_back_to_default_slug(): void
    {
        Setting::set(SparePartsCatalog::SETTING_KEY, 999999);

        $this->get('/collections/pieces-moteurs')->assertOk()->assertSee(self::BANNER, false);
    }

    public function test_banner_on_spare_part_product_page_with_product_context(): void
    {
        $product = $this->singleVariantProduct('SKU-PIECE-42');
        $this->partsChild->products()->attach($product->id);
        $variant = $product->variants->first();

        $html = $this->get('/produits/'.$product->defaultUrl->slug)->assertOk()
            ->assertSee(self::BANNER, false)
            ->getContent();

        $query = $this->bannerQuery($html);
        $this->assertSame('part_photo', $query['mode'] ?? null);
        $this->assertSame((string) $variant->id, $query['product'] ?? null);
        $this->assertSame('SKU-PIECE-42', $query['ref'] ?? null);
        $this->assertSame('/produits/'.$product->defaultUrl->slug, $query['from'] ?? null);

        // Bandeau placé après le bloc prix/actions (bas de fiche).
        $this->assertGreaterThan(strpos($html, self::ADVICE), strpos($html, self::BANNER));
    }

    public function test_no_banner_on_product_outside_spare_parts_tree(): void
    {
        $product = $this->singleVariantProduct('SKU-AUTRE-1');
        $this->otherRoot->products()->attach($product->id);

        $this->get('/produits/'.$product->defaultUrl->slug)->assertOk()->assertDontSee(self::BANNER, false);
    }

    public function test_advice_button_on_any_product_page_for_guest(): void
    {
        $this->assertGuest();

        $product = $this->singleVariantProduct('SKU-CONSEIL-7');
        $variant = $product->variants->first();

        $html = $this->get('/produits/'.$product->defaultUrl->slug)->assertOk()
            ->assertSee(self::ADVICE, false)
            ->assertSee('Demander un conseil ou un devis')
            ->getContent();

        $query = $this->queryOf($html, self::ADVICE);
        // Mode général = mode absent de l'URL.
        $this->assertArrayNotHasKey('mode', $query);
        $this->assertSame((string) $variant->id, $query['product'] ?? null);
        $this->assertSame('SKU-CONSEIL-7', $query['ref'] ?? null);
        $this->assertSame('/produits/'.$product->defaultUrl->slug, $query['from'] ?? null);
    }

    public function test_admin_can_designate_the_spare_parts_root(): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'spare-parts@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');

        $this->get(StorefrontSettings::getUrl())->assertOk()
            ->assertSee('Catégorie racine « Pièces détachées »');

        $options = SparePartsCatalog::collectionOptions();
        $this->assertSame('— Moteurs', $options[$this->partsChild->id] ?? null);

        Livewire::test(StorefrontSettings::class)
            ->set('data.contact_phone', '0102030405')
            ->set('data.spare_parts_collection_id', (string) $this->otherRoot->id)
            ->call('save')
            ->assertHasNoErrors();

        Setting::forget();
        $this->assertSame($this->otherRoot->id, Setting::get(SparePartsCatalog::SETTING_KEY));
        $this->assertSame($this->otherRoot->id, SparePartsCatalog::root()?->id);
    }

    /** @return array<string, string> */
    private function bannerQuery(string $html): array
    {
        return $this->queryOf($html, 'data-testid="part-help-banner-cta"');
    }

    /**
     * Query string du lien porteur de l'attribut donné.
     *
     * @return array<string, string>
     */
    private function queryOf(string $html, string $marker): array
    {
        $this->assertSame(1, preg_match('/<a\s+href="([^"]+)"[^>]*'.preg_quote($marker, '/').'/', $html, $m), 'Lien introuvable : '.$marker);

        $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('/demande-de-devis', $href);

        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

        return $query;
    }

    private function singleVariantProduct(string $sku): Product
    {
        $product = Product::query()->has('urls')->with(['defaultUrl', 'variants'])->first();
        $this->assertNotNull($product, 'Le seeder doit fournir un produit avec une URL.');

        $product->variants->skip(1)->each->delete();
        $product->variants->first()->update(['sku' => $sku]);

        return $product->fresh(['defaultUrl', 'variants']);
    }

    private function makeRoot(string $name, string $slug): LunarCollection
    {
        $collection = $this->makeCollection($name);
        $collection->saveAsRoot();
        $this->setSlug($collection, $slug);

        return $collection;
    }

    private function makeChild(LunarCollection $parent, string $name, string $slug): LunarCollection
    {
        $collection = $this->makeCollection($name);
        $collection->appendToNode($parent->refresh())->save();
        $this->setSlug($collection, $slug);

        return $collection;
    }

    private function makeCollection(string $name): LunarCollection
    {
        return new LunarCollection([
            'collection_group_id' => $this->groupId,
            'type' => 'static',
            'sort' => 'custom',
            'attribute_data' => collect([
                'name' => new TranslatedText(collect(['fr' => new LunarText($name)])),
            ]),
        ]);
    }

    private function setSlug(LunarCollection $collection, string $slug): void
    {
        $collection->urls()->delete();

        Url::create([
            'language_id' => Language::query()->value('id'),
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'slug' => $slug,
            'default' => true,
        ]);
    }
}
