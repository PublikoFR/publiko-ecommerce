<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Formulaire : source credentials + services + grille --}}
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

        {{-- Récap services --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Services enregistrés</x-slot>
            <x-slot name="description">Vue en lecture seule — l'édition se fait dans le formulaire ci-dessus.</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">Code</th>
                            <th class="py-2 pr-4">Libellé</th>
                            <th class="py-2">Actif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($this->getServices() as $service)
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $service['code'] }}</td>
                                <td class="py-2 pr-4">{{ $service['label'] }}</td>
                                <td class="py-2">
                                    @if ($service['enabled'])
                                        <x-filament::badge color="success">Actif</x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray">Inactif</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-3 text-center text-gray-500 dark:text-gray-400">
                                    Aucun service configuré.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Récap grille --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Grille tarifaire</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">Poids max (kg)</th>
                            <th class="py-2 pr-4">Prix</th>
                            <th class="py-2">Service</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($this->getGrid() as $bracket)
                            <tr>
                                <td class="py-2 pr-4 font-mono">{{ $bracket['max_kg'] }}</td>
                                <td class="py-2 pr-4">{{ $this->formatCents($bracket['price']) }}</td>
                                <td class="py-2 text-gray-500">{{ $bracket['service_code'] ?? 'tous' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-3 text-center text-gray-500 dark:text-gray-400">
                                    Aucun palier tarifaire configuré.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
