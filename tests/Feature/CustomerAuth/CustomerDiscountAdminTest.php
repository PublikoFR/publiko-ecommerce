<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\FieldTypes\Text;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Customer;
use Lunar\Models\Product;
use Pko\CustomerAuth\Filament\Resources\CustomerDiscountResource;
use Pko\CustomerAuth\Filament\Resources\CustomerDiscountResource\Pages\ManageCustomerDiscounts;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoViewCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\RelationManagers\CustomerDiscountsRelationManager;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\RelationManagers\NegotiatedPricesRelationManager;
use Pko\CustomerAuth\Models\CustomerDiscount;
use Pko\CustomerAuth\Support\CustomerDiscountTargets;
use Tests\TestCase;

/**
 * Back-office des remises client : onglet de la fiche client, vue globale,
 * case « Non remisable » de la fiche produit.
 */
class CustomerDiscountAdminTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $admin = Staff::query()->first();
        $this->assertNotNull($admin, 'Un Staff admin seedé est requis.');
        $this->actingAs($admin, 'staff');

        $this->customer = Customer::factory()->create(['company_name' => 'Société Alpha']);
        $this->brand = Brand::create(['name' => 'Marque Remise']);
    }

    private function relationManager(?Customer $customer = null): Testable
    {
        return Livewire::test(CustomerDiscountsRelationManager::class, [
            'ownerRecord' => $customer ?? $this->customer,
            'pageClass' => PkoViewCustomer::class,
        ]);
    }

    /** @return array{0: Collection, 1: Collection, 2: Collection} */
    private function makeTree(): array
    {
        $group = CollectionGroup::factory()->create(['name' => 'Arbre remises']);
        $make = fn (string $name) => Collection::factory()->create([
            'collection_group_id' => $group->id,
            'attribute_data' => collect(['name' => new Text($name)]),
        ]);

        $family = $make('Outillage');
        $sub = $make('Perçage');
        $family->appendNode($sub);
        $subSub = $make('Forets');
        $sub->appendNode($subSub);

        return [$family->fresh(), $sub->fresh(), $subSub->fresh()];
    }

    public function test_discounts_tab_is_registered_after_negotiated_prices(): void
    {
        $relations = PkoCustomerResource::getRelations();

        $this->assertContains(CustomerDiscountsRelationManager::class, $relations);
        $this->assertGreaterThan(
            array_search(NegotiatedPricesRelationManager::class, $relations, true),
            array_search(CustomerDiscountsRelationManager::class, $relations, true),
        );
    }

    public function test_creates_a_discount_from_the_customer_tab(): void
    {
        $this->relationManager()
            ->assertSuccessful()
            ->callTableAction('create', data: [
                'target_type' => CustomerDiscount::TARGET_BRAND,
                'target_id' => $this->brand->id,
                'percent' => '12.5',
            ])
            ->assertHasNoTableActionErrors();

        $discount = CustomerDiscount::query()->sole();
        $this->assertSame($this->customer->id, $discount->customer_id);
        $this->assertSame(CustomerDiscount::TARGET_BRAND, $discount->target_type);
        $this->assertSame($this->brand->id, $discount->target_id);
        $this->assertSame('12.50', (string) $discount->percent);
    }

    public function test_percent_must_be_strictly_positive_and_at_most_100(): void
    {
        foreach (['0', '-5', '100.01', '12.345'] as $invalid) {
            $this->relationManager()
                ->callTableAction('create', data: [
                    'target_type' => CustomerDiscount::TARGET_BRAND,
                    'target_id' => $this->brand->id,
                    'percent' => $invalid,
                ])
                ->assertHasTableActionErrors(['percent']);
        }

        $this->relationManager()
            ->callTableAction('create', data: [
                'target_type' => CustomerDiscount::TARGET_BRAND,
                'target_id' => $this->brand->id,
                'percent' => '100',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, CustomerDiscount::query()->count());
    }

    public function test_rejects_a_duplicate_customer_target_but_allows_other_customers(): void
    {
        CustomerDiscount::create([
            'customer_id' => $this->customer->id,
            'target_type' => CustomerDiscount::TARGET_BRAND,
            'target_id' => $this->brand->id,
            'percent' => '10',
        ]);

        $this->relationManager()
            ->callTableAction('create', data: [
                'target_type' => CustomerDiscount::TARGET_BRAND,
                'target_id' => $this->brand->id,
                'percent' => '20',
            ])
            ->assertHasTableActionErrors(['target_id' => 'unique']);

        // Même client, autre type de cible : pas un doublon.
        [$family] = $this->makeTree();
        $this->relationManager()
            ->callTableAction('create', data: [
                'target_type' => CustomerDiscount::TARGET_COLLECTION,
                'target_id' => $family->id,
                'percent' => '5',
            ])
            ->assertHasNoTableActionErrors();

        // Même cible, autre client : autorisé.
        $other = Customer::factory()->create();
        $this->relationManager($other)
            ->callTableAction('create', data: [
                'target_type' => CustomerDiscount::TARGET_BRAND,
                'target_id' => $this->brand->id,
                'percent' => '20',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(3, CustomerDiscount::query()->count());
    }

    public function test_editing_a_discount_keeps_its_own_target_valid(): void
    {
        $discount = CustomerDiscount::create([
            'customer_id' => $this->customer->id,
            'target_type' => CustomerDiscount::TARGET_BRAND,
            'target_id' => $this->brand->id,
            'percent' => '10',
        ]);

        $this->relationManager()
            ->callTableAction('edit', $discount, data: ['percent' => '15'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('15.00', (string) $discount->refresh()->percent);
    }

    public function test_collection_options_show_the_readable_tree_path(): void
    {
        [$family, $sub, $subSub] = $this->makeTree();

        $options = app(CustomerDiscountTargets::class)->options(CustomerDiscount::TARGET_COLLECTION);
        $flat = [];
        array_walk_recursive($options, function (string $label, int $id) use (&$flat): void {
            $flat[$id] = $label;
        });

        $this->assertSame('Outillage', $flat[$family->id]);
        $this->assertSame('Outillage › Perçage', $flat[$sub->id]);
        $this->assertSame('Outillage › Perçage › Forets', $flat[$subSub->id]);
    }

    public function test_brand_family_target_label_includes_the_brand(): void
    {
        $familyId = DB::table('pko_brand_families')->insertGetId([
            'brand_id' => $this->brand->id,
            'code' => 'ACM',
            'label' => 'Accessoires',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            'Marque Remise · ACM — Accessoires',
            app(CustomerDiscountTargets::class)->label(CustomerDiscount::TARGET_BRAND_FAMILY, $familyId),
        );
    }

    public function test_global_list_shows_all_customers_and_filters(): void
    {
        [$family] = $this->makeTree();
        $other = Customer::factory()->create(['company_name' => 'Société Beta']);

        $a = CustomerDiscount::create(['customer_id' => $this->customer->id, 'target_type' => CustomerDiscount::TARGET_BRAND, 'target_id' => $this->brand->id, 'percent' => '10']);
        $b = CustomerDiscount::create(['customer_id' => $other->id, 'target_type' => CustomerDiscount::TARGET_COLLECTION, 'target_id' => $family->id, 'percent' => '7.5']);
        $c = CustomerDiscount::create(['customer_id' => $other->id, 'target_type' => CustomerDiscount::TARGET_BRAND, 'target_id' => $this->brand->id, 'percent' => '3']);

        Livewire::test(ManageCustomerDiscounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$a, $b, $c])
            ->assertSee('Société Alpha')
            ->assertSee('Outillage')
            ->assertSee('-7,5 %')
            ->filterTable('customer_id', $other->id)
            ->assertCanSeeTableRecords([$b, $c])
            ->assertCanNotSeeTableRecords([$a]);

        Livewire::test(ManageCustomerDiscounts::class)
            ->filterTable('target', ['target_type' => CustomerDiscount::TARGET_BRAND, 'target_id' => $this->brand->id])
            ->assertCanSeeTableRecords([$a, $c])
            ->assertCanNotSeeTableRecords([$b]);

        Livewire::test(ManageCustomerDiscounts::class)
            ->filterTable('target', ['target_type' => CustomerDiscount::TARGET_COLLECTION])
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a, $c]);
    }

    public function test_global_list_creates_and_edits_with_duplicate_guard(): void
    {
        $existing = CustomerDiscount::create(['customer_id' => $this->customer->id, 'target_type' => CustomerDiscount::TARGET_BRAND, 'target_id' => $this->brand->id, 'percent' => '10']);

        Livewire::test(ManageCustomerDiscounts::class)
            ->callAction('create', data: [
                'customer_id' => $this->customer->id,
                'target_type' => CustomerDiscount::TARGET_BRAND,
                'target_id' => $this->brand->id,
                'percent' => '20',
            ])
            ->assertHasActionErrors(['target_id' => 'unique']);

        $other = Customer::factory()->create();
        Livewire::test(ManageCustomerDiscounts::class)
            ->callAction('create', data: [
                'customer_id' => $other->id,
                'target_type' => CustomerDiscount::TARGET_BRAND,
                'target_id' => $this->brand->id,
                'percent' => '20',
            ])
            ->assertHasNoActionErrors();

        Livewire::test(ManageCustomerDiscounts::class)
            ->callTableAction('edit', $existing, data: ['percent' => '25'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('25.00', (string) $existing->refresh()->percent);
        $this->assertSame(2, CustomerDiscount::query()->count());
    }

    public function test_global_list_is_reachable(): void
    {
        $this->get(CustomerDiscountResource::getUrl('index'))->assertOk();
    }

    public function test_product_page_saves_the_not_discountable_flag(): void
    {
        $product = Product::query()->firstOrFail();

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->assertSet('notDiscountable', false)
            ->set('notDiscountable', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $product->refresh()->pko_not_discountable);

        Livewire::test(EditProductUnified::class, ['record' => $product->id])
            ->assertSet('notDiscountable', true)
            ->set('notDiscountable', false)
            ->call('save');

        $this->assertFalse((bool) $product->refresh()->pko_not_discountable);
    }
}
