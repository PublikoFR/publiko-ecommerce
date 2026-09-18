<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Pko\ShippingCommon\Support\ParcelDimensionsCalculator;
use Pko\ShippingCommon\Support\WeightCalculator;
use Tests\TestCase;

/**
 * Poids et dimensions du colis calculés sur une vraie commande : la ligne de frais de
 * port (purchasable_type = ShippingOption, un DataType) ne doit jamais être résolue.
 */
class OrderParcelCalculatorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function orderWithShippingLine(): Order
    {
        $currency = Currency::query()->firstOrFail();

        $order = Order::factory()->create([
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'channel_id' => Channel::query()->firstOrFail()->id,
        ]);

        $variant = ProductVariant::factory()->create([
            'weight_value' => 2.5,
            'weight_unit' => 'kg',
            'length_value' => 40,
            'length_unit' => 'cm',
            'width_value' => 30,
            'width_unit' => 'cm',
            'height_value' => 20,
            'height_unit' => 'cm',
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'type' => 'physical',
            'purchasable_type' => ProductVariant::morphName(),
            'purchasable_id' => $variant->id,
            'quantity' => 2,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'purchasable_type' => ShippingOption::class,
            'purchasable_id' => 1,
            'quantity' => 1,
        ]);

        return $order->fresh();
    }

    public function test_le_poids_ignore_la_ligne_de_frais_de_port(): void
    {
        $this->assertSame(5.0, WeightCalculator::fromOrder($this->orderWithShippingLine()));
    }

    public function test_les_dimensions_ignorent_la_ligne_de_frais_de_port(): void
    {
        $dimensions = ParcelDimensionsCalculator::fromOrder($this->orderWithShippingLine(), 'chronopost');

        $this->assertSame(['length' => 40.0, 'width' => 30.0, 'height' => 20.0], $dimensions);
    }
}
