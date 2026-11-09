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
        @elseif ($activeTab === 'sections')
            @livewire('admin-nav::home-sections-form', [], key('home-sections'))
        @endif
    </div>
</x-filament-panels::page>
