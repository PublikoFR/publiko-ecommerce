<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Support\CatalogAvailability;
use App\Support\CustomerGroupGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Tests\TestCase;

/**
 * Sémantique « pas de ligne = visible » sur les pivots de visibilité catalogue.
 * Cf. App\Support\CatalogAvailability pour le raisonnement.
 */
class CatalogAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const COLLECTION_PIVOT = 'lunar_collection_customer_group';

    private const PRODUCT_PIVOT = 'lunar_customer_group_product';

    protected function setUp(): void
    {
        parent::setUp();

        Language::firstOrCreate(
            ['code' => 'fr'],
            ['name' => 'Français', 'default' => true]
        );
    }

    private function makeCollection(string $name = 'Catégorie'): Collection
    {
        $group = CollectionGroup::firstOrCreate(
            ['handle' => 'main'],
            ['name' => 'Main']
        );

        return Collection::create([
            'collection_group_id' => $group->id,
            'type' => 'static',
            'attribute_data' => collect([
                'name' => new TranslatedText(collect(['fr' => new Text($name)])),
            ]),
        ]);
    }

    private function makeProduct(string $name = 'Produit'): Product
    {
        $type = ProductType::firstOrCreate(['name' => 'Standard']);

        return Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => collect([
                'name' => new TranslatedText(collect(['fr' => new Text($name)])),
            ]),
        ]);
    }

    /**
     * Le cœur du correctif : sans l'observer (ou s'il se déclenchait AVANT celui
     * du trait Lunar), la création d'une collection sèmerait une ligne par groupe
     * client existant. Ce test est le garde-fou de la contrainte d'ordre décrite
     * dans CatalogAvailabilityObserver.
     */
    public function test_creating_a_collection_seeds_no_rows(): void
    {
        CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);
        CustomerGroup::create(['name' => 'Particuliers', 'handle' => 'particuliers', 'default' => true]);

        $collection = $this->makeCollection();

        $this->assertSame(
            0,
            DB::table(self::COLLECTION_PIVOT)->where('collection_id', $collection->id)->count(),
            'Lunar a semé des lignes de visibilité : l’observer ne passe pas après celui du trait.'
        );
    }

    public function test_creating_a_product_seeds_no_rows(): void
    {
        CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);

        $product = $this->makeProduct();

        $this->assertSame(
            0,
            DB::table(self::PRODUCT_PIVOT)->where('product_id', $product->id)->count()
        );
    }

    /** Le symptôme d'origine : « Pro — encore utilisé (494 collection(s)) ». */
    public function test_group_is_deletable_despite_existing_catalog(): void
    {
        CustomerGroup::create(['name' => 'Particuliers', 'handle' => 'particuliers', 'default' => true]);

        $this->makeCollection('Chauffage');
        $this->makeCollection('Sanitaire');
        $this->makeProduct('Chaudière');

        $group = CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);

        $this->assertNull(CustomerGroupGuard::blockReason($group));
    }

    /** Une ligne dont tous les flags sont à 1 ne restreint rien : elle ne bloque pas. */
    public function test_permissive_row_does_not_block_deletion(): void
    {
        $collection = $this->makeCollection();
        $group = CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);

        $collection->customerGroups()->attach($group, [
            'enabled' => true,
            'visible' => true,
        ]);

        $this->assertNull(CustomerGroupGuard::blockReason($group));

        // ... mais elle doit être détachée avant le delete, sinon FK 1451.
        $this->assertSame(1, CustomerGroupGuard::detachCatalogAvailability($group));
        $group->delete();
        $this->assertNull(CustomerGroup::find($group->id));
    }

    /** Une restriction réelle, elle, bloque bien la suppression. */
    public function test_restrictive_row_blocks_deletion(): void
    {
        $collection = $this->makeCollection();
        $group = CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);

        $collection->customerGroups()->attach($group, [
            'enabled' => false,
            'visible' => false,
        ]);

        $reason = CustomerGroupGuard::blockReason($group);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('restriction(s) sur des catégories', $reason);
    }

    public function test_restriction_count_ignores_other_groups(): void
    {
        $collection = $this->makeCollection();
        $pro = CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);
        $vip = CustomerGroup::create(['name' => 'VIP', 'handle' => 'vip', 'default' => false]);

        $collection->customerGroups()->attach($pro, ['enabled' => false, 'visible' => false]);

        $this->assertSame(1, CatalogAvailability::restrictionCount(self::COLLECTION_PIVOT, (int) $pro->id));
        $this->assertSame(0, CatalogAvailability::restrictionCount(self::COLLECTION_PIVOT, (int) $vip->id));
    }

    /**
     * Le point critique : la commande ne purge PAS la table, elle ne retire que
     * les lignes portant la signature du semis. Une restriction saisie à la main
     * doit survivre.
     */
    public function test_prune_removes_seeded_rows_but_keeps_manual_restrictions(): void
    {
        $collection = $this->makeCollection();
        $group = CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);

        // (a) Ligne semée : flags = group.default, horodatée avec le parent.
        DB::table(self::COLLECTION_PIVOT)->insert([
            'collection_id' => $collection->id,
            'customer_group_id' => $group->id,
            'enabled' => false,
            'visible' => false,
            'starts_at' => $collection->created_at,
            'ends_at' => null,
            'created_at' => $collection->created_at,
            'updated_at' => $collection->created_at,
        ]);

        // (b) Restriction manuelle : mêmes flags, mais posée plus tard.
        $other = $this->makeCollection('Sanitaire');
        $later = $collection->created_at->copy()->addHour();
        DB::table(self::COLLECTION_PIVOT)->insert([
            'collection_id' => $other->id,
            'customer_group_id' => $group->id,
            'enabled' => false,
            'visible' => false,
            'starts_at' => $later,
            'ends_at' => null,
            'created_at' => $later,
            'updated_at' => $later,
        ]);

        $this->artisan('pko:catalog-availability:prune', ['--apply' => true])
            ->assertExitCode(0);

        $remaining = DB::table(self::COLLECTION_PIVOT)->pluck('collection_id')->all();

        $this->assertNotContains($collection->id, $remaining, 'La ligne semée aurait dû être supprimée.');
        $this->assertContains($other->id, $remaining, 'Une restriction manuelle a été détruite.');
    }

    /** Sans --apply, la commande ne touche à rien. */
    public function test_prune_is_dry_run_by_default(): void
    {
        $collection = $this->makeCollection();
        $group = CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);

        DB::table(self::COLLECTION_PIVOT)->insert([
            'collection_id' => $collection->id,
            'customer_group_id' => $group->id,
            'enabled' => false,
            'visible' => false,
            'starts_at' => $collection->created_at,
            'ends_at' => null,
            'created_at' => $collection->created_at,
            'updated_at' => $collection->created_at,
        ]);

        $this->artisan('pko:catalog-availability:prune')->assertExitCode(0);

        $this->assertSame(1, DB::table(self::COLLECTION_PIVOT)->count());
    }

    /** Une ligne avec date de fin est une planification volontaire : intouchable. */
    public function test_prune_keeps_rows_with_an_end_date(): void
    {
        $collection = $this->makeCollection();
        $group = CustomerGroup::create(['name' => 'Pro', 'handle' => 'pro', 'default' => false]);

        DB::table(self::COLLECTION_PIVOT)->insert([
            'collection_id' => $collection->id,
            'customer_group_id' => $group->id,
            'enabled' => false,
            'visible' => false,
            'starts_at' => $collection->created_at,
            'ends_at' => $collection->created_at->copy()->addMonth(),
            'created_at' => $collection->created_at,
            'updated_at' => $collection->created_at,
        ]);

        $this->artisan('pko:catalog-availability:prune', ['--apply' => true])->assertExitCode(0);

        $this->assertSame(1, DB::table(self::COLLECTION_PIVOT)->count());
    }

    public function test_prune_is_idempotent(): void
    {
        $this->artisan('pko:catalog-availability:prune', ['--apply' => true])->assertExitCode(0);
        $this->artisan('pko:catalog-availability:prune', ['--apply' => true])->assertExitCode(0);
    }
}
