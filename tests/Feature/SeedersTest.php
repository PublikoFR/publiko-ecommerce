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
}
