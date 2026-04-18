<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-neutral-900">Achat rapide</h1>
        <p class="text-neutral-600 mt-1 text-sm">Saisissez vos références et quantités, ou collez un tableau Excel.</p>
    </div>

    @if ($lastResult)
        <x-ui.alert variant="success">{{ $lastResult }}</x-ui.alert>
    @endif

    <x-ui.card padding="none">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50">
                    <tr class="text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                        <th class="px-4 py-3 w-40">Référence</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 w-32 text-center">Quantité</th>
                        <th class="px-4 py-3 w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($rows as $idx => $row)
                        <tr wire:key="row-{{ $idx }}">
                            <td class="px-4 py-2">
                                <input type="text" wire:model.blur="rows.{{ $idx }}.sku" placeholder="SKU ou référence" class="w-full rounded border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500 font-mono" />
                                @if (isset($errors[$idx]))
                                    <p class="text-xs text-danger-600 mt-1">{{ $errors[$idx] }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm">
                                @if (isset($resolved[$idx]))
                                    <div>
                                        <p class="font-semibold text-neutral-900">{{ $resolved[$idx]['variant']->product->translateAttribute('name') }}</p>
                                        <p class="text-xs text-neutral-500">Stock : {{ $resolved[$idx]['variant']->stock }}</p>
                                    </div>
                                @else
                                    <span class="text-neutral-400 italic">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" min="1" wire:model.blur="rows.{{ $idx }}.quantity" class="w-20 mx-auto block rounded border-neutral-300 text-sm text-center focus:border-primary-500 focus:ring-primary-500 no-spinner" />
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" wire:click="removeRow({{ $idx }})" class="text-neutral-400 hover:text-danger-600"><x-ui.icon name="trash" class="w-4 h-4" /></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-neutral-100 flex items-center justify-between gap-3">
            <x-ui.button variant="ghost" size="sm" wire:click="addRow" icon="plus">Ajouter une ligne</x-ui.button>
            <x-ui.button variant="primary" wire:click="submit" icon="cart">Ajouter au panier</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card padding="lg">
        <h2 class="font-bold text-neutral-900 mb-2">Import depuis Excel</h2>
        <p class="text-xs text-neutral-500 mb-3">Collez vos lignes au format <code>SKU;qté</code> (une ligne par article). Les séparateurs <code>;</code> <code>,</code> ou tab sont acceptés.</p>
        <x-ui.textarea wire:model="pasteInput" placeholder="SKU-123;5&#10;SKU-456;12" rows="6" />
        <div class="mt-3 flex justify-end">
            <x-ui.button variant="outline" size="sm" wire:click="parsePaste">Analyser et remplir</x-ui.button>
        </div>
    </x-ui.card>
</div>
