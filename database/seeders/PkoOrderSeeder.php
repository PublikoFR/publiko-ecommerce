<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

class PkoOrderSeeder extends Seeder
{
    private const STATUSES = [
        'awaiting-payment',
        'payment-received',
        'in-preparation',
        'dispatched',
        'delivered',
        'cancelled',
    ];

    public function run(): void
    {
        $channel = Channel::query()->where('default', true)->firstOrFail();
        $france = Country::query()->where('iso2', 'FR')->firstOrFail();
        $customers = Customer::query()->get();
        $variants = ProductVariant::query()->take(20)->get();

        if ($customers->isEmpty() || $variants->isEmpty()) {
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
            $reference = sprintf('MDE-%06d', $i);

            if (Order::query()->where('reference', $reference)->exists()) {
                continue;
            }

            $customer = $customers->random();
            $status = self::STATUSES[array_rand(self::STATUSES)];
            $lineCount = random_int(1, 3);
            $pickedVariants = $variants->random(min($lineCount, $variants->count()));

            $subTotal = 0;
            $taxTotal = 0;

            $linesData = [];

            foreach ($pickedVariants as $variant) {
                $qty = random_int(1, 3);
                $unitPrice = random_int(5000, 150000);
                $lineSub = $unitPrice * $qty;
                $lineTax = (int) round($lineSub * 0.2);

                $subTotal += $lineSub;
                $taxTotal += $lineTax;

                $linesData[] = [
                    'variant' => $variant,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'sub_total' => $lineSub,
                    'tax_total' => $lineTax,
                ];
            }

            $shipping = 1500;
            $total = $subTotal + $taxTotal + $shipping;

            $order = Order::query()->create([
                'channel_id' => $channel->id,
                'customer_id' => $customer->id,
                'new_customer' => false,
                'user_id' => null,
                'status' => $status,
                'reference' => $reference,
                'sub_total' => $subTotal,
                'discount_total' => 0,
                'shipping_total' => $shipping,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => $taxTotal,
                'total' => $total,
                'notes' => null,
                'currency_code' => 'EUR',
                'compare_currency_code' => 'EUR',
                'exchange_rate' => 1,
                'placed_at' => $status === 'awaiting-payment' ? null : now()->subDays(random_int(1, 30)),
                'meta' => null,
            ]);

            foreach ($linesData as $line) {
                /** @var ProductVariant $variant */
                $variant = $line['variant'];

                OrderLine::query()->create([
                    'order_id' => $order->id,
                    'purchasable_type' => ProductVariant::morphName(),
                    'purchasable_id' => $variant->id,
                    'type' => 'physical',
                    'description' => optional($variant->product)->translateAttribute('name') ?? 'Produit MDE',
                    'option' => null,
                    'identifier' => $variant->sku ?? Str::random(8),
                    'unit_price' => $line['unit_price'],
                    'unit_quantity' => 1,
                    'quantity' => $line['qty'],
                    'sub_total' => $line['sub_total'],
                    'discount_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'tax_total' => $line['tax_total'],
                    'total' => $line['sub_total'] + $line['tax_total'],
                    'notes' => null,
                    'meta' => null,
                ]);
            }

            $this->createAddress($order, $france, $customer, 'shipping');
            $this->createAddress($order, $france, $customer, 'billing');
        }
    }

    private function createAddress(Order $order, Country $country, Customer $customer, string $type): void
    {
        OrderAddress::query()->create([
            'order_id' => $order->id,
            'country_id' => $country->id,
            'title' => $customer->title,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'company_name' => $customer->company_name,
            'tax_identifier' => $customer->tax_identifier,
            'line_one' => '12 rue de la Fabrication',
            'line_two' => null,
            'line_three' => null,
            'city' => 'Lyon',
            'state' => 'Rhône',
            'postcode' => '69003',
            'type' => $type,
        ]);
    }
}
