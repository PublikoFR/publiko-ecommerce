<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Facades\Pricing;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\ProductVariant;
use Pko\CustomerAuth\Models\NegotiatedPrice;
use Tests\TestCase;

class NegotiatedPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function makeCustomerUser(): array
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $customer->users()->attach($user);
        $customer->customerGroups()->attach(CustomerGroup::getDefault());

        return [$user, $customer];
    }

    private function makeVariantWithBasePrice(int $baseCents): ProductVariant
    {
        $variant = ProductVariant::factory()->create();
        $variant->prices()->create([
            'price' => $baseCents,
            'currency_id' => Currency::getDefault()->id,
            'min_quantity' => 1,
        ]);

        return $variant;
    }

    public function test_negotiated_price_overrides_when_lower(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariantWithBasePrice(10000); // 100,00 € HT

        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 7000, // 70,00 € négocié
        ]);

        $matched = Pricing::for($variant)->user($user)->get()->matched;

        $this->assertSame(7000, $matched->price->value);
    }

    public function test_base_price_wins_when_lower_than_negotiated(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariantWithBasePrice(5000); // 50,00 € HT

        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 7000, // négocié plus cher → ne doit pas s'appliquer
        ]);

        $matched = Pricing::for($variant)->user($user)->get()->matched;

        $this->assertSame(5000, $matched->price->value);
    }

    public function test_guest_is_not_affected(): void
    {
        $variant = $this->makeVariantWithBasePrice(10000);

        $matched = Pricing::for($variant)->get()->matched;

        $this->assertSame(10000, $matched->price->value);
    }
}
