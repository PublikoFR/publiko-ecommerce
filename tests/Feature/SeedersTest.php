<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Brand;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_required_baseline(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(50, Product::query()->count());
        $this->assertGreaterThanOrEqual(3, LunarCollection::query()->count());
        $this->assertSame(2, CustomerGroup::query()->count());
        $this->assertSame(10, Order::query()->count());
        $this->assertGreaterThanOrEqual(5, Brand::query()->count());
    }

    public function test_shipping_seeder_creates_zone_methods_rates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $zone = ShippingZone::query()
            ->where('name', 'France métropolitaine')
            ->firstOrFail();

        // Must be the plural 'countries' type expected by the table-rate resolver,
        // otherwise ShippingManifest resolves zero options at checkout.
        $this->assertSame('countries', $zone->type);
        $this->assertTrue(
            $zone->countries()->where('iso2', 'FR')->exists(),
            'Shipping zone must be attached to FR country.',
        );

        // Methods must be scheduled against the customer groups, otherwise the
        // customer-group scope rejects every rate and no option is available.
        $standardMethod = ShippingMethod::query()->where('code', 'mde-standard')->firstOrFail();
        $this->assertTrue(
            $standardMethod->customerGroups()->count() > 0,
            'Shipping methods must be scheduled against customer groups.',
        );

        $this->assertSame(3, ShippingMethod::query()->count());
        $this->assertSame(3, ShippingRate::query()->count());

        $standard = ShippingMethod::query()->where('code', 'mde-standard')->firstOrFail();
        $this->assertSame('ship-by', $standard->driver);
        $this->assertSame('weight', $standard->data['charge_by']);

        $standardRate = ShippingRate::query()
            ->where('shipping_method_id', $standard->id)
            ->firstOrFail();

        $this->assertSame(4, $standardRate->prices()->count());

        $pickup = ShippingMethod::query()->where('code', 'mde-pickup')->firstOrFail();
        $this->assertSame('collection', $pickup->driver);

        $free = ShippingMethod::query()->where('code', 'mde-free')->firstOrFail();
        $this->assertSame('free-shipping', $free->driver);
        $this->assertSame(50000, $free->data['minimum_spend']['EUR']);
    }
}
