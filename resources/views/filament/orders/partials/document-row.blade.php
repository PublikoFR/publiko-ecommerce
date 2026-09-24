{{-- Ligne d'un document comptable (facture ou avoir Pennylane) : $document, $class. --}}
<div @class(['flex items-start justify-between gap-3', $class ?? null])>
    <div class="flex min-w-0 items-start gap-2.5">
        <x-filament::icon
            :icon="$document['type'] === 'Avoir' ? 'heroicon-o-receipt-refund' : 'heroicon-o-document-text'"
            class="mt-0.5 h-5 w-5 shrink-0 text-gray-400"
        />
        <div class="min-w-0">
            <p class="truncate font-medium text-gray-950 dark:text-white">{{ $document['label'] }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @switch($document['state'])
                    @case('ready') Émise dans Pennylane @break
                    @case('failed') Émission en échec @break
                    @default Émission en cours
                @endswitch
            </p>
        </div>
    </div>
    @if ($document['url'])
        <div class="flex shrink-0 items-center gap-2">
        @if ($document['view_url'] ?? null)
            <x-filament::button
                tag="a"
                :href="$document['view_url']"
                target="_blank"
                rel="noopener noreferrer"
                color="gray"
                size="xs"
                icon="heroicon-m-eye"
            >
                Voir
            </x-filament::button>
        @endif
        <x-filament::button
            tag="a"
            :href="$document['url']"
            color="gray"
            size="xs"
            icon="heroicon-m-arrow-down-tray"
        >
            PDF
        </x-filament::button>
        </div>
    @endif
</div>
