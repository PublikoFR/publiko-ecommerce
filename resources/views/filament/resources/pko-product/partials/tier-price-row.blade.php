@props(['index', 'tier'])

<tr class="border-t border-gray-200 dark:border-white/10">
    <td class="px-3 py-2">
        <select
            wire:model="tierPrices.{{ $index }}.customer_group_id"
            class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
        >
            <option value="">Tous les clients</option>
            @foreach ($this->customerGroupOptions as $group)
                <option value="{{ $group->id }}">{{ $group->name }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-3 py-2 w-32">
        <input
            type="number" min="1"
            wire:model="tierPrices.{{ $index }}.min_quantity"
            class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900 text-right"
        />
    </td>
    <td class="px-3 py-2 w-40">
        <div class="relative">
            <input
                type="text"
                wire:model="tierPrices.{{ $index }}.price"
                placeholder="0.00"
                class="w-full text-sm font-mono border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900 text-right tabular-nums pr-6"
            />
            <span class="absolute right-2 top-1.5 text-xs text-gray-500">€</span>
        </div>
    </td>
    <td class="px-3 py-2 text-right">
        <button
            type="button"
            wire:click="removeTierPrice({{ $index }})"
            class="text-xs text-danger-600 hover:text-danger-700"
        >
            Retirer
        </button>
    </td>
</tr>
