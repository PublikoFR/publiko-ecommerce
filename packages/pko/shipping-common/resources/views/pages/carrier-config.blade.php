<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Services : table CRUD (ajout / modification / suppression en ligne) --}}
        @livewire('pko-shipping.carrier-services-table', ['carrierCode' => $this->getCarrierCode()], key('services-'.$this->getCarrierCode()))

        {{-- Grille tarifaire : table CRUD --}}
        @livewire('pko-shipping.carrier-grid-table', ['carrierCode' => $this->getCarrierCode()], key('grid-'.$this->getCarrierCode()))

        {{-- Credentials + mode de tarification --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit">Enregistrer</x-filament::button>
            </div>
        </form>

        {{-- Statut --}}
        <x-filament::section>
            <x-slot name="heading">État</x-slot>
            <x-slot name="description">
                Source actuelle : <strong>{{ $this->getCurrentSource() === 'db' ? 'base de données (chiffré)' : '.env' }}</strong>
            </x-slot>

            @if ($this->isConfigured())
                <div class="flex items-start gap-3 rounded-lg bg-success-50 p-4 dark:bg-success-500/10">
                    <x-heroicon-o-check-circle class="h-6 w-6 flex-shrink-0 text-success-600 dark:text-success-400" />
                    <div>
                        <p class="font-semibold text-success-700 dark:text-success-300">Transporteur configuré</p>
                        <p class="mt-1 text-sm text-success-700/80 dark:text-success-300/80">
                            Cliquez sur « Tester les credentials » pour valider la connexion.
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-3 rounded-lg bg-warning-50 p-4 dark:bg-warning-500/10">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 flex-shrink-0 text-warning-600 dark:text-warning-400" />
                    <div>
                        <p class="font-semibold text-warning-700 dark:text-warning-300">Configuration incomplète</p>
                        <p class="mt-1 text-sm text-warning-700/80 dark:text-warning-300/80">
                            Renseignez les credentials via le formulaire ci-dessus.
                        </p>
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
