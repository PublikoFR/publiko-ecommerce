@props(['variant'])

@php
    $basePrice = $variant->prices->first();
    $priceDisplay = $basePrice
        ? number_format(($basePrice->price->value ?? 0) / 100, 2, ',', ' ')
        : '—';
    $active = ($variant->purchasable ?? 'always') !== 'never';
@endphp

<tr class="border-t border-gray-200 dark:border-white/10">
    <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
        {{ $variant->getDescription() ?: 'Variante #' . $variant->id }}
    </td>
    <td class="px-3 py-2 text-xs font-mono text-gray-600 dark:text-gray-400">
        {{ $variant->sku ?: '—' }}
    </td>
    <td class="px-3 py-2 text-sm font-mono text-right tabular-nums">
        {{ $priceDisplay }} €
    </td>
    <td class="px-3 py-2 w-24">
        <input
            type="number"
            value="{{ $variant->stock }}"
            wire:change="updateVariantStock({{ $variant->id }}, $event.target.value)"
            class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900 text-right tabular-nums"
        />
    </td>
    <td class="px-3 py-2 text-center">
        <button
            type="button"
            wire:click="updateVariantPurchasable({{ $variant->id }}, {{ $active ? 'false' : 'true' }})"
            @class([
                'relative inline-flex h-5 w-9 rounded-full transition-colors',
                'bg-primary-600' => $active,
                'bg-gray-300 dark:bg-gray-600' => ! $active,
            ])
        >
            <span
                @class([
                    'absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                    'translate-x-4' => $active,
                    'translate-x-0' => ! $active,
                ])
            ></span>
        </button>
    </td>
</tr>
