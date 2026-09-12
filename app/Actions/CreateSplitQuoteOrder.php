<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Lunar\Base\OrderReferenceGeneratorInterface;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

/**
 * Creates a companion awaiting-quote order from the quote lines that were
 * removed from the cart during the split flow (stored in cart.meta['split_pending']).
 *
 * The payable order must already exist in DB before this action runs.
 * Both orders are linked via meta:
 *   - quote order  → meta['split_from']     = payable order ID  (int)
 *   - quote order  → meta['split_group']    = shared UUID        (string)
 *   - payable order → meta['split_children'] = [quote order ID]  (int[])
 */
final class CreateSplitQuoteOrder
{
    /**
     * @param  array<int, array<string, mixed>>  $splitPending  Lines captured by applySplit()
     */
    public function execute(Order $payableOrder, array $splitPending, string $splitGroup): Order
    {
        // ── 1. Compute order-level totals from the split lines ─────────────────
        $subTotal = (int) array_sum(array_column($splitPending, 'sub_total'));
        $taxTotal = (int) array_sum(array_column($splitPending, 'tax_total'));
        $lineTotal = (int) array_sum(array_column($splitPending, 'total'));

        // ── 2. Insert order row (bypass Lunar casts — same pattern as tests) ───
        $quoteOrderId = DB::table('lunar_orders')->insertGetId([
            'channel_id' => $payableOrder->channel_id,
            'status' => 'awaiting-quote',
            'reference' => 'TEMP-'.$payableOrder->id,
            'customer_reference' => $payableOrder->customer_reference,
            'customer_id' => $payableOrder->customer_id,
            'user_id' => $payableOrder->user_id,
            'currency_code' => $payableOrder->currency_code,
            'compare_currency_code' => $payableOrder->compare_currency_code,
            'exchange_rate' => $payableOrder->exchange_rate,
            'sub_total' => $subTotal,
            'discount_total' => 0,
            'shipping_total' => 0, // operator will calculate quote shipping
            'tax_total' => $taxTotal,
            'total' => $lineTotal,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'pko_site_name' => $payableOrder->pko_site_name,
            'meta' => json_encode([
                'split_from' => $payableOrder->id,
                'split_group' => $splitGroup,
            ]),
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var Order $quoteOrder */
        $quoteOrder = Order::findOrFail($quoteOrderId);

        // ── 3. Generate a proper Lunar reference ──────────────────────────────
        $reference = app(OrderReferenceGeneratorInterface::class)->generate($quoteOrder);
        $quoteOrder->update(['reference' => $reference]);

        // ── 4. Copy billing and shipping addresses ─────────────────────────────
        $this->copyAddress($payableOrder, $quoteOrder, 'shipping');
        $this->copyAddress($payableOrder, $quoteOrder, 'billing');

        // ── 5. Insert order lines from the captured split_pending ──────────────
        $now = now();
        foreach ($splitPending as $line) {
            DB::table('lunar_order_lines')->insert([
                'order_id' => $quoteOrderId,
                'purchasable_type' => $line['purchasable_type'],
                'purchasable_id' => $line['purchasable_id'],
                'type' => 'physical',
                'description' => $line['description'],
                'identifier' => $line['identifier'],
                'unit_price' => (int) $line['unit_price'],
                'unit_quantity' => (int) ($line['unit_quantity'] ?? 1),
                'quantity' => (int) $line['quantity'],
                'sub_total' => (int) $line['sub_total'],
                'discount_total' => 0,
                'tax_breakdown' => '[]',
                'tax_total' => (int) ($line['tax_total'] ?? 0),
                'total' => (int) ($line['total'] ?? 0),
                'notes' => null,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── 6. Link payable order to its sibling (meta['split_children']) ──────
        $payableMeta = (array) ($payableOrder->meta ?? []);
        $existing = (array) ($payableMeta['split_children'] ?? []);
        $payableOrder->forceFill([
            'meta' => array_merge($payableMeta, [
                'split_children' => array_merge($existing, [$quoteOrderId]),
            ]),
        ])->save();

        return $quoteOrder->fresh();
    }

    private function copyAddress(Order $source, Order $destination, string $type): void
    {
        $address = $type === 'shipping'
            ? $source->shippingAddress
            : $source->billingAddress;

        if (! $address) {
            return;
        }

        OrderAddress::create(array_merge(
            $address->only([
                'first_name', 'last_name', 'company_name',
                'line_one', 'line_two', 'line_three',
                'city', 'state', 'postcode', 'country_id',
                'contact_email', 'contact_phone', 'delivery_instructions',
                'shipping_option',
            ]),
            [
                'order_id' => $destination->id,
                'type' => $type,
            ]
        ));
    }
}
