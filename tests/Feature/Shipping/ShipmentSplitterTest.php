<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Collection;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Shipping\ShipmentGroup;
use Pko\ShippingCommon\Shipping\ShipmentSplitter;
use Tests\TestCase;

class ShipmentSplitterTest extends TestCase
{
    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeLine(?int $supplierId): object
    {
        $product = (object) ['pko_supplier_id' => $supplierId];

        return (object) ['purchasable' => (object) ['product' => $product]];
    }

    private function makeSupplier(int $id, bool $blNeutre): object
    {
        return (object) ['id' => $id, 'bl_neutre' => $blNeutre];
    }

    private function splitter(): ShipmentSplitter
    {
        return new ShipmentSplitter;
    }

    /** @param ShipmentGroup[] $groups */
    private function originMap(array $groups): array
    {
        $map = [];
        foreach ($groups as $g) {
            $map[$g->origin] = $g->lineCount;
        }

        return $map;
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_100_percent_weklo_order_produces_one_group(): void
    {
        $lines = new Collection([
            $this->makeLine(null),
            $this->makeLine(null),
        ]);

        $groups = $this->splitter()->split($lines, collect());

        $this->assertCount(1, $groups);
        $this->assertSame(CarrierShipment::ORIGIN_WEKLO, $groups[0]->origin);
        $this->assertSame(2, $groups[0]->lineCount);
    }

    public function test_mixed_order_produces_three_groups(): void
    {
        $supplierDirect = $this->makeSupplier(10, true);   // bl_neutre → supplier_direct
        $supplierTransit = $this->makeSupplier(20, false); // !bl_neutre → supplier_via_weklo

        $lines = new Collection([
            $this->makeLine(null),  // weklo
            $this->makeLine(10),    // supplier_direct
            $this->makeLine(20),    // supplier_via_weklo
            $this->makeLine(null),  // weklo (same group)
        ]);

        $suppliers = collect([$supplierDirect, $supplierTransit])->keyBy('id');

        $groups = $this->splitter()->split($lines, $suppliers);
        $map = $this->originMap($groups);

        $this->assertCount(3, $groups);
        $this->assertSame(2, $map[CarrierShipment::ORIGIN_WEKLO]);
        $this->assertSame(1, $map[CarrierShipment::ORIGIN_SUPPLIER_DIRECT]);
        $this->assertSame(1, $map[CarrierShipment::ORIGIN_SUPPLIER_VIA_WEKLO]);
    }

    public function test_unknown_supplier_id_falls_back_to_weklo(): void
    {
        $lines = new Collection([
            $this->makeLine(999), // supplier ID not in the suppliers collection
        ]);

        $groups = $this->splitter()->split($lines, collect());

        $this->assertCount(1, $groups);
        $this->assertSame(CarrierShipment::ORIGIN_WEKLO, $groups[0]->origin);
    }

    public function test_all_supplier_direct_produces_one_group(): void
    {
        $supplier = $this->makeSupplier(5, true);
        $lines = new Collection([
            $this->makeLine(5),
            $this->makeLine(5),
        ]);

        $groups = $this->splitter()->split($lines, collect([$supplier])->keyBy('id'));

        $this->assertCount(1, $groups);
        $this->assertSame(CarrierShipment::ORIGIN_SUPPLIER_DIRECT, $groups[0]->origin);
        $this->assertSame(2, $groups[0]->lineCount);
    }

    public function test_line_with_null_purchasable_falls_back_to_weklo(): void
    {
        $line = (object) ['purchasable' => null];
        $lines = new Collection([$line]);

        $groups = $this->splitter()->split($lines, collect());

        $this->assertCount(1, $groups);
        $this->assertSame(CarrierShipment::ORIGIN_WEKLO, $groups[0]->origin);
    }
}
