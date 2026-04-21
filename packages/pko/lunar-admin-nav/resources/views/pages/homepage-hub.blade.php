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
            @livewire(\Pko\AdminNav\Filament\Widgets\HomeSlidesTable::class, key: 'home-slides')
        @elseif ($activeTab === 'tiles')
            @livewire(\Pko\AdminNav\Filament\Widgets\HomeTilesTable::class, key: 'home-tiles')
        @elseif ($activeTab === 'offers')
            @livewire(\Pko\AdminNav\Filament\Widgets\HomeOffersTable::class, key: 'home-offers')
        @endif
    </div>
</x-filament-panels::page>
