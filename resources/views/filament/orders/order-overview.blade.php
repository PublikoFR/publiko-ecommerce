{{-- Vue d'ensemble de la commande (colonne latérale de la fiche admin). --}}
@php($data = $getState() ?? [])

<div class="divide-y divide-gray-100 dark:divide-white/10">
    {{-- Référence + statut, date --}}
    <div class="pb-4">
        <div class="flex items-start justify-between gap-3">
            <div x-data="{ copied: false }" class="flex min-w-0 items-center gap-1.5">
                <span class="truncate text-lg font-semibold tracking-tight text-gray-950 dark:text-white">{{ $data['reference'] }}</span>
                <button
                    type="button"
                    title="Copier la référence"
                    class="shrink-0 rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    x-on:click="navigator.clipboard.writeText(@js($data['reference'])); copied = true; setTimeout(() => copied = false, 1500)"
                >
                    <x-filament::icon icon="heroicon-o-clipboard" class="h-4 w-4" x-show="! copied" />
                    <x-filament::icon icon="heroicon-o-check" class="h-4 w-4 text-success-600" x-show="copied" x-cloak />
                </button>
            </div>
            <x-filament::badge :color="$data['status']['color']" class="shrink-0">
                {{ $data['status']['label'] }}
            </x-filament::badge>
        </div>
        @if ($data['date'])
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $data['date'] }}</p>
        @endif
    </div>

    {{-- Client --}}
    <div class="py-4">
        @if ($data['customer'])
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-2.5">
                    <x-filament::icon icon="heroicon-o-user-circle" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-950 dark:text-white">{{ $data['customer']['name'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $data['customer']['type'] }} · {{ $data['customer']['orders'] }}</p>
                    </div>
                </div>
                <x-filament::button tag="a" :href="$data['customer']['url']" color="gray" size="xs" class="shrink-0">
                    Fiche client
                </x-filament::button>
            </div>
        @else
            <div class="flex items-start gap-2.5">
                <x-filament::icon icon="heroicon-o-user-circle" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
                <div class="min-w-0">
                    @if (filled($data['guest']['name'] ?? null))
                        <p class="truncate font-medium text-gray-950 dark:text-white">{{ $data['guest']['name'] }}</p>
                    @endif
                    <p class="text-sm text-gray-500 dark:text-gray-400">Commande sans compte client</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Détails facultatifs --}}
    @if (count($data['details']))
        <dl class="space-y-2 pt-4 text-sm">
            @foreach ($data['details'] as $row)
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                    <dd x-data="{ copied: false }" class="flex min-w-0 items-center gap-1.5 text-end font-medium text-gray-950 dark:text-white">
                        <span class="break-words">{{ $row['value'] }}</span>
                        @if ($row['copyable'])
                            <button
                                type="button"
                                title="Copier"
                                class="shrink-0 rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                x-on:click="navigator.clipboard.writeText(@js($row['value'])); copied = true; setTimeout(() => copied = false, 1500)"
                            >
                                <x-filament::icon icon="heroicon-o-clipboard" class="h-4 w-4" x-show="! copied" />
                                <x-filament::icon icon="heroicon-o-check" class="h-4 w-4 text-success-600" x-show="copied" x-cloak />
                            </button>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
