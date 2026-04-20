@php
    $statePath = $getStatePath();
    $livewire = $getLivewire();
    /** @var array<string, string> $rows */
    $rows = $getRows();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="w-10 px-3 py-2 text-left font-medium">Inclure</th>
                    <th class="px-3 py-2 text-left font-medium">Paramètre</th>
                    <th class="px-3 py-2 text-left font-medium">Valeur</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $property => $label)
                    @php
                        $value = (string) ($livewire->{$property} ?? '');
                    @endphp
                    <tr class="bg-white dark:bg-gray-900/40">
                        <td class="px-3 py-2 align-top">
                            <input
                                type="checkbox"
                                wire:model.live="{{ $statePath }}.{{ $property }}"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-3 py-2 align-top font-medium text-gray-900 dark:text-gray-100">
                            {{ $label }}
                            <div class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                <code>${{ $property }}</code>
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top text-gray-700 dark:text-gray-300">
                            @if ($value === '')
                                <span class="italic text-gray-400 dark:text-gray-500">— vide —</span>
                            @else
                                <pre class="whitespace-pre-wrap break-words font-sans text-sm leading-snug">{{ $value }}</pre>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-dynamic-component>
