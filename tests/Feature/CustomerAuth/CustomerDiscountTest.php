<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Facades\Pricing;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\CustomerAuth\Models\CustomerDiscount;
use Pko\CustomerAuth\Models\NegotiatedPrice;
use Pko\CustomerAuth\Pricing\CustomerDiscountResolver;
use Pko\CustomerAuth\Pricing\CustomerPriceBreakdown;
use Tests\TestCase;

class CustomerDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array{0: User, 1: Customer} */
    private function makeCustomerUser(): array
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $customer->users()->attach($user);
        $customer->customerGroups()->attach(CustomerGroup::getDefault());

        return [$user, $customer];
    }

    private function makeVariant(int $baseCents, array $productAttributes = []): ProductVariant
    {
        $product = Product::factory()->create();
        if ($productAttributes !== []) {
            DB::table('lunar_products')->where('id', $product->id)->update($productAttributes);
        }

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->prices()->create([
            'price' => $baseCents,
            'currency_id' => Currency::getDefault()->id,
            'min_quantity' => 1,
        ]);

        return $variant;
    }

    private function discount(Customer $customer, string $type, int $targetId, string $percent): void
    {
        CustomerDiscount::create([
            'customer_id' => $customer->id,
            'target_type' => $type,
            'target_id' => $targetId,
            'percent' => $percent,
        ]);
    }

    /** @return array{0: Collection, 1: Collection, 2: Collection} famille > sous-famille > sous-sous-famille */
    private function makeTree(): array
    {
        $group = CollectionGroup::factory()->create();
        $family = Collection::factory()->create(['collection_group_id' => $group->id]);
        $sub = Collection::factory()->create(['collection_group_id' => $group->id]);
        $family->appendNode($sub);
        $subSub = Collection::factory()->create(['collection_group_id' => $group->id]);
        $sub->appendNode($subSub);

        return [$family->fresh(), $sub->fresh(), $subSub->fresh()];
    }

    private function price(ProductVariant $variant, User $user): int
    {
        return Pricing::for($variant)->user($user)->get()->matched->price->value;
    }

    public function test_discount_on_ancestor_collection_covers_descendant_product(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        [$family, , $subSub] = $this->makeTree();
        $variant = $this->makeVariant(10000);
        $variant->product->collections()->attach($subSub->id, ['position' => 1]);

        $this->discount($customer, CustomerDiscount::TARGET_COLLECTION, $family->id, '15');

        $this->assertSame(8500, $this->price($variant, $user));
    }

    public function test_discount_on_descendant_does_not_cover_parent_product(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        [$family, , $subSub] = $this->makeTree();
        $variant = $this->makeVariant(10000);
        $variant->product->collections()->attach($family->id, ['position' => 1]);

        $this->discount($customer, CustomerDiscount::TARGET_COLLECTION, $subSub->id, '15');

        $this->assertSame(10000, $this->price($variant, $user));
    }

    public function test_strongest_discount_wins_without_cumulation(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        [$family, $sub] = $this->makeTree();
        $brand = Brand::factory()->create();
        $variant = $this->makeVariant(10000, ['brand_id' => $brand->id]);
        $variant->product->collections()->attach($sub->id, ['position' => 1]);

        $this->discount($customer, CustomerDiscount::TARGET_COLLECTION, $family->id, '10');
        $this->discount($customer, CustomerDiscount::TARGET_COLLECTION, $sub->id, '5');
        $this->discount($customer, CustomerDiscount::TARGET_BRAND, $brand->id, '12.5');

        $breakdown = app(CustomerDiscountResolver::class)->priceFor($variant, $user);

        $this->assertSame(8750, $breakdown->finalCents);
        $this->assertSame(10000, $breakdown->originalCents);
        $this->assertSame(CustomerPriceBreakdown::SOURCE_DISCOUNT, $breakdown->source);
        $this->assertSame(12.5, $breakdown->appliedDiscountPercent());
    }

    public function test_brand_family_discount_applies(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('lunar_products', 'pko_brand_family_id')) {
            $this->markTestSkipped('Colonne pko_brand_family_id absente.');
        }

        [$user, $customer] = $this->makeCustomerUser();
        $brand = Brand::factory()->create();
        $familyId = DB::table('pko_brand_families')->insertGetId([
            'brand_id' => $brand->id,
            'code' => 'FAM1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $variant = $this->makeVariant(10000, ['pko_brand_family_id' => $familyId]);

        $this->discount($customer, CustomerDiscount::TARGET_BRAND_FAMILY, $familyId, '20');

        $this->assertSame(8000, $this->price($variant, $user));
    }

    public function test_negotiated_price_wins_when_lower_than_discounted_price(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $brand = Brand::factory()->create();
        $variant = $this->makeVariant(10000, ['brand_id' => $brand->id]);
        $this->discount($customer, CustomerDiscount::TARGET_BRAND, $brand->id, '10'); // 90,00

        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 8000,
        ]);

        $breakdown = app(CustomerDiscountResolver::class)->priceFor($variant, $user);

        $this->assertSame(8000, $breakdown->finalCents);
        $this->assertSame(CustomerPriceBreakdown::SOURCE_NEGOTIATED, $breakdown->source);
        $this->assertNull($breakdown->appliedDiscountPercent());
        $this->assertSame(10.0, $breakdown->discountPercent());
    }

    public function test_discounted_price_wins_when_lower_than_negotiated_price(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $brand = Brand::factory()->create();
        $variant = $this->makeVariant(10000, ['brand_id' => $brand->id]);
        $this->discount($customer, CustomerDiscount::TARGET_BRAND, $brand->id, '30'); // 70,00

        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 8000,
        ]);

        $this->assertSame(7000, $this->price($variant, $user));
    }

    public function test_not_discountable_product_ignores_discount_but_keeps_negotiated_price(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $brand = Brand::factory()->create();
        $variant = $this->makeVariant(10000, ['brand_id' => $brand->id, 'pko_not_discountable' => true]);
        $this->discount($customer, CustomerDiscount::TARGET_BRAND, $brand->id, '30');

        $this->assertSame(10000, $this->price($variant, $user));

        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 9000,
        ]);
        app(CustomerDiscountResolver::class)->flush();

        $breakdown = app(CustomerDiscountResolver::class)->priceFor($variant, $user);
        $this->assertSame(9000, $breakdown->finalCents);
        $this->assertNull($breakdown->discountPercent());
    }

    public function test_customer_without_discount_and_guest_keep_catalog_price(): void
    {
        [$user] = $this->makeCustomerUser();
        $variant = $this->makeVariant(10000);

        $breakdown = app(CustomerDiscountResolver::class)->priceFor($variant, $user);
        $this->assertSame(10000, $breakdown->finalCents);
        $this->assertSame(CustomerPriceBreakdown::SOURCE_CATALOG, $breakdown->source);
        $this->assertFalse($breakdown->isDiscounted());

        $this->assertSame(10000, Pricing::for($variant)->get()->matched->price->value);
    }

    public function test_listing_preload_avoids_n_plus_one(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $brand = Brand::factory()->create();
        $variants = collect(range(1, 5))->map(fn () => $this->makeVariant(10000, ['brand_id' => $brand->id]));
        $this->discount($customer, CustomerDiscount::TARGET_BRAND, $brand->id, '10');

        $resolver = app(CustomerDiscountResolver::class);
        $resolver->flush();
        $user->load('customers');

        // Chauffe : charge les remises du client une fois.
        $resolver->bestBasisPoints($variants->first(), [$customer->id]);
        $resolver->preloadVariants($variants);

        DB::enableQueryLog();
        foreach ($variants as $variant) {
            $this->assertSame(1000, $resolver->bestBasisPoints($variant, [$customer->id]));
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_rounding_is_done_in_integer_cents(): void
    {
        $this->assertSame(866, CustomerDiscountResolver::applyBasisPoints(999, 1333));
        $this->assertSame(0, CustomerDiscountResolver::applyBasisPoints(999, 10000));
    }
}
