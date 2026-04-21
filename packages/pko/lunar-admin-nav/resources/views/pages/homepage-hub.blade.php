<x-filament-panels::page>
    <x-filament::tabs>
        @foreach ($this->getTabs() as $key => $label)
            <x-filament::tabs.item
                :active="$activeTab === $key"
                wire:click="$set('activeTab', '{{ $key }}')"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <div class="mt-6">
        @if ($activeTab === 'slides')
            @livewire(\Pko\AdminNav\Filament\Widgets\HomeSlidesTable::class, [], 'home-slides')
        @elseif ($activeTab === 'tiles')
            @livewire(\Pko\AdminNav\Filament\Widgets\HomeTilesTable::class, [], 'home-tiles')
        @elseif ($activeTab === 'offers')
            @livewire(\Pko\AdminNav\Filament\Widgets\HomeOffersTable::class, [], 'home-offers')
        @endif
    </div>
</x-filament-panels::page>
