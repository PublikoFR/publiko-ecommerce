<?php

declare(strict_types=1);

namespace Tests\Feature\Pennylane;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Pennylane\Dto\InvoiceLineData;
use Pko\Pennylane\Services\OrderToDeliveryNoteMapper;
use Pko\Pennylane\Services\OrderToInvoiceMapper;
use Pko\Pennylane\Services\TransactionToCreditNoteMapper;
use Tests\TestCase;

/**
 * Ventilation de l'éco-participation (modèle « dont ») sur les documents
 * Pennylane : une ligne « Éco-participation » sous chaque produit qui en a une,
 * sans jamais modifier le total HT ni la TVA du document.
 */
class EcotaxInvoiceLinesTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pennylane.customer_invoice_template_id' => 1]);
        Http::preventStrayRequests();
        Http::fake();

        Currency::factory()->create(['code' => 'EUR', 'default' => true]);
        $customer = Customer::factory()->create();

        $this->order = Order::withoutEvents(fn () => Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'payment-received',
            'currency_code' => 'EUR',
            'compare_currency_code' => 'EUR',
            'reference' => 'CMD-ECO',
        ]));
        DB::table('lunar_order_lines')->where('order_id', $this->order->id)->delete();
    }

    private function line(
        string $type,
        string $description,
        string $identifier,
        int $quantity,
        int $subTotal,
        int $tax,
        int $discount = 0,
        ?int $ecotaxUnit = null,
    ): void {
        DB::table('lunar_order_lines')->insert([
            'order_id' => $this->order->id,
            'purchasable_type' => 'product_variant',
            'purchasable_id' => 0,
            'type' => $type,
            'description' => $description,
            'identifier' => $identifier,
            'unit_price' => intdiv($subTotal, $quantity),
            'unit_quantity' => 1,
            'quantity' => $quantity,
            'sub_total' => $subTotal,
            'discount_total' => $discount,
            'tax_breakdown' => '[]',
            'tax_total' => $tax,
            'total' => $subTotal - $discount + $tax,
            'meta' => $ecotaxUnit === null ? null : json_encode(['ecotax_cents' => $ecotaxUnit]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int,InvoiceLineData> */
    private function lines(): array
    {
        return app(OrderToInvoiceMapper::class)->lines($this->order->fresh());
    }

    /**
     * Base HT par taux (cents) et TVA totale (cents, arrondie par taux).
     *
     * @param  array<int,InvoiceLineData>  $lines
     * @return array{0: array<string,int>, 1: int}
     */
    private function totals(array $lines): array
    {
        $bases = [];
        foreach ($lines as $line) {
            $key = (string) $line->vatRate;
            $bases[$key] = ($bases[$key] ?? 0.0) + $line->quantity * $line->unitAmount;
        }
        $bases = array_map(fn (float $v): int => (int) round($v * 100), $bases);
        ksort($bases);

        $vat = 0;
        foreach ($bases as $rate => $base) {
            $vat += (int) round($base * (float) $rate / 100);
        }

        return [$bases, $vat];
    }

    /** Totaux qu'aurait la facture sans ventilation (meta d'éco-taxe retirée). */
    private function totalsWithoutEcotax(): array
    {
        DB::table('lunar_order_lines')->where('order_id', $this->order->id)->update(['meta' => null]);

        return $this->totals($this->lines());
    }

    public function test_invoice_without_ecotax_is_unchanged(): void
    {
        $this->line('physical', 'Perceuse', 'SKU-1', 2, 10000, 2000);
        $this->line('shipping', 'Livraison', 'SHIP', 1, 990, 198);

        $lines = $this->lines();

        $this->assertSame(['Perceuse [SKU-1]', 'Livraison [SHIP]'], array_map(fn (InvoiceLineData $l) => $l->label, $lines));
        $this->assertEqualsWithDelta(50.0, $lines[0]->unitAmount, 1e-9);
        $this->assertSame(20.0, $lines[0]->vatRate);
    }

    public function test_ecotax_line_is_added_under_each_product(): void
    {
        $this->line('physical', 'Frigo', 'SKU-F', 2, 100000, 20000, 0, 1050);
        $this->line('physical', 'Vis', 'SKU-V', 10, 500, 100);
        $this->line('physical', 'Lave-linge', 'SKU-L', 1, 50000, 10000, 0, 1300);
        $this->line('shipping', 'Livraison', 'SHIP', 1, 990, 198);

        $lines = $this->lines();

        $this->assertSame([
            'Frigo [SKU-F]',
            'Éco-participation – Frigo [SKU-F]',
            'Vis [SKU-V]',
            'Lave-linge [SKU-L]',
            'Éco-participation – Lave-linge [SKU-L]',
            'Livraison [SHIP]',
        ], array_map(fn (InvoiceLineData $l) => $l->label, $lines));

        // Frigo : 1 000 € HT pour 2 → 489,50 € + 10,50 € d'éco-participation par unité.
        $this->assertSame(2.0, $lines[0]->quantity);
        $this->assertEqualsWithDelta(489.5, $lines[0]->unitAmount, 1e-9);
        $this->assertSame(2.0, $lines[1]->quantity);
        $this->assertEqualsWithDelta(10.5, $lines[1]->unitAmount, 1e-9);
        $this->assertSame($lines[0]->vatRate, $lines[1]->vatRate);
        $this->assertSame('piece', $lines[1]->unit);

        $this->assertSame($this->totals($lines), $this->totalsWithoutEcotax());
    }

    public function test_discount_is_charged_entirely_to_the_product_line(): void
    {
        // 3 × 33,33 € HT, remise 10,00 € : HT remisé 89,99 €, non divisible par 3.
        $this->line('physical', 'Radiateur', 'SKU-R', 3, 9999, 1800, 1000, 250);

        $lines = $this->lines();

        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta(2.5, $lines[1]->unitAmount, 1e-9);
        $this->assertEqualsWithDelta((8999 - 750) / 3 / 100, $lines[0]->unitAmount, 1e-9);
        $this->assertSame(20.0, $lines[0]->vatRate);
        $this->assertSame(20.0, $lines[1]->vatRate);

        $this->assertSame($this->totals($lines), $this->totalsWithoutEcotax());
    }

    public function test_ecotax_is_capped_when_discount_exceeds_product_share(): void
    {
        Log::spy();
        // 2 × 10,00 € HT, remise 19,00 € → 1,00 € HT restant < 2 × 3,00 € d'éco-taxe.
        $this->line('physical', 'Écran', 'SKU-E', 2, 2000, 20, 1900, 300);

        $lines = $this->lines();

        $this->assertCount(2, $lines);
        $this->assertSame(0.0, $lines[0]->unitAmount);
        $this->assertEqualsWithDelta(0.5, $lines[1]->unitAmount, 1e-9);
        $this->assertSame(20.0, $lines[1]->vatRate);
        Log::shouldHaveReceived('warning')->once();

        $this->assertSame($this->totals($lines), $this->totalsWithoutEcotax());
    }

    public function test_mixed_rates_keep_their_own_ecotax_rate(): void
    {
        $this->line('physical', 'Frigo', 'SKU-F', 1, 40000, 8000, 0, 1050);
        $this->line('physical', 'Bouilloire', 'SKU-B', 4, 4000, 400, 0, 17);

        $lines = $this->lines();

        $this->assertSame(20.0, $lines[1]->vatRate);
        $this->assertSame(10.0, $lines[3]->vatRate);
        $this->assertEqualsWithDelta(0.17, $lines[3]->unitAmount, 1e-9);

        $this->assertSame($this->totals($lines), $this->totalsWithoutEcotax());
    }

    public function test_credit_note_prorata_is_unaffected_by_ecotax_lines(): void
    {
        $this->line('physical', 'Frigo', 'SKU-F', 2, 100000, 20000, 0, 1050);
        $this->line('shipping', 'Livraison', 'SHIP', 1, 1000, 0);
        $this->order->update(['total' => 121000]);

        $refund = Transaction::withoutEvents(fn () => Transaction::factory()->create([
            'order_id' => $this->order->id,
            'type' => 'refund',
            'success' => true,
            'amount' => 121000,
        ]));

        $lines = app(TransactionToCreditNoteMapper::class)->build($refund->fresh(), 900, 1)->lines;

        // Remboursement total : une ligne négative par taux, HT de la facture entière.
        $byRate = [];
        foreach ($lines as $line) {
            $byRate[(string) $line->vatRate] = (int) round($line->unitAmount * 100);
        }
        ksort($byRate);
        $this->assertSame(['0' => -1000, '20' => -100000], $byRate);
    }

    public function test_delivery_note_carries_the_same_breakdown_without_shipping(): void
    {
        $this->line('physical', 'Frigo', 'SKU-F', 2, 100000, 20000, 0, 1050);
        $this->line('shipping', 'Livraison', 'SHIP', 1, 990, 198);

        $payload = app(OrderToDeliveryNoteMapper::class)->build($this->order->fresh(), 900)->toArray();

        $this->assertSame(
            ['Frigo [SKU-F]', 'Éco-participation – Frigo [SKU-F]'],
            array_column($payload['invoice_lines'], 'label'),
        );
        $this->assertSame('10.50', $payload['invoice_lines'][1]['raw_currency_unit_price']);
    }
}
