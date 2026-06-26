<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Shipping;

use Illuminate\Support\Collection;
use Pko\ShippingCommon\Models\CarrierShipment;

/**
 * Groups order lines by stock origin so that one CarrierShipment can be
 * created per origin instead of a single shipment for the whole order.
 *
 * Kept as a pure service (no DB queries, no Eloquent calls) so it can be
 * unit-tested with stdClass mock objects.
 */
final class ShipmentSplitter
{
    /**
     * @param  Collection<int, object>  $lines  Order lines already loaded with purchasable.product
     * @param  Collection<int|string, object>  $suppliers  Supplier models keyed by id
     * @return ShipmentGroup[]
     */
    public function split(Collection $lines, Collection $suppliers): array
    {
        $counts = [];

        foreach ($lines as $line) {
            $origin = $this->resolveOrigin($line, $suppliers);
            $counts[$origin] = ($counts[$origin] ?? 0) + 1;
        }

        $groups = [];
        foreach ($counts as $origin => $count) {
            $groups[] = new ShipmentGroup($origin, $count);
        }

        return $groups;
    }

    private function resolveOrigin(object $line, Collection $suppliers): string
    {
        $supplierId = $line->purchasable?->product?->pko_supplier_id ?? null;

        if ($supplierId === null) {
            return CarrierShipment::ORIGIN_WEKLO;
        }

        $supplier = $suppliers->get($supplierId);

        if ($supplier === null) {
            return CarrierShipment::ORIGIN_WEKLO;
        }

        return ($supplier->bl_neutre ?? false)
            ? CarrierShipment::ORIGIN_SUPPLIER_DIRECT
            : CarrierShipment::ORIGIN_SUPPLIER_VIA_WEKLO;
    }
}
