<?php

declare(strict_types=1);

namespace Pko\QuickOrder\Services;

use Lunar\Models\ProductVariant;

class SkuResolver
{
    /**
     * @param  array<int, array{sku: string, quantity: int}>  $rows
     * @return array{resolved: array<int, array{variant: ProductVariant, quantity: int, sku: string}>, errors: array<int, string>}
     */
    public function resolve(array $rows): array
    {
        $skus = array_filter(array_map(fn ($r) => trim((string) ($r['sku'] ?? '')), $rows));
        if ($skus === []) {
            return ['resolved' => [], 'errors' => []];
        }

        $variants = ProductVariant::query()
            ->whereIn('sku', $skus)
            ->with(['product.defaultUrl', 'product.thumbnail'])
            ->get()
            ->keyBy(fn (ProductVariant $v) => strtolower((string) $v->sku));

        $resolved = [];
        $errors = [];

        foreach ($rows as $idx => $row) {
            $sku = strtolower(trim((string) ($row['sku'] ?? '')));
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            if ($sku === '') {
                continue;
            }
            if (! $variants->has($sku)) {
                $errors[$idx] = 'Référence "'.$row['sku'].'" introuvable.';

                continue;
            }
            $resolved[$idx] = [
                'variant' => $variants[$sku],
                'quantity' => $qty,
                'sku' => (string) $row['sku'],
            ];
        }

        return ['resolved' => $resolved, 'errors' => $errors];
    }
}
