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
        @if ($activeTab === 'tiers')
            @livewire(\Pko\AdminNav\Filament\Widgets\LoyaltyTiersTable::class, key: 'loyalty-tiers')
        @elseif ($activeTab === 'gifts')
            @livewire(\Pko\AdminNav\Filament\Widgets\LoyaltyGiftsTable::class, key: 'loyalty-gifts')
        @elseif ($activeTab === 'points')
            @livewire(\Pko\AdminNav\Filament\Widgets\LoyaltyPointsTable::class, key: 'loyalty-points')
        @elseif ($activeTab === 'settings')
            <x-filament-panels::form wire:submit="saveSettings">
                {{ $this->settingsForm }}

                <x-filament-panels::form.actions
                    :actions="[
                        \Filament\Actions\Action::make('save')
                            ->label(__('admin-nav::admin.hubs.loyalty.settings.save'))
                            ->submit('saveSettings'),
                    ]"
                />
            </x-filament-panels::form>
        @endif
    </div>
</x-filament-panels::page>
