<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status --}}
        <x-filament::section>
            <x-slot name="heading">État de la configuration</x-slot>
            <x-slot name="description">
                Credentials API Colissimo, services activés et grille tarifaire.
            </x-slot>

            @if ($this->isConfigured())
                <div class="flex items-start gap-3 rounded-lg bg-success-50 p-4 dark:bg-success-500/10">
                    <x-heroicon-o-check-circle class="h-6 w-6 flex-shrink-0 text-success-600 dark:text-success-400" />
                    <div>
                        <p class="font-semibold text-success-700 dark:text-success-300">Colissimo est configuré</p>
                        <p class="mt-1 text-sm text-success-700/80 dark:text-success-300/80">
                            Contrat et mot de passe présents. Cliquez sur « Tester les credentials » pour valider.
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-3 rounded-lg bg-warning-50 p-4 dark:bg-warning-500/10">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 flex-shrink-0 text-warning-600 dark:text-warning-400" />
                    <div>
                        <p class="font-semibold text-warning-700 dark:text-warning-300">Configuration incomplète</p>
                        <p class="mt-1 text-sm text-warning-700/80 dark:text-warning-300/80">
                            Renseignez <code>COLISSIMO_CONTRACT</code> et <code>COLISSIMO_PASSWORD</code> dans <code>.env</code>.
                        </p>
                    </div>
                </div>
            @endif
        </x-filament::section>

        {{-- Credentials --}}
        <x-filament::section>
            <x-slot name="heading">Credentials</x-slot>

            <div class="divide-y divide-gray-100 dark:divide-white/5">
                <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Numéro de contrat (<code>COLISSIMO_CONTRACT</code>)
                        </p>
                        <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->hasContractNumber() ? $this->getContractNumber() : 'Non renseigné' }}
                        </p>
                    </div>
                    @if ($this->hasContractNumber())
                        <x-filament::badge color="success" icon="heroicon-m-check">Défini</x-filament::badge>
                    @else
                        <x-filament::badge color="danger" icon="heroicon-m-x-mark">Manquant</x-filament::badge>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-4 py-3 last:pb-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Mot de passe (<code>COLISSIMO_PASSWORD</code>)
                        </p>
                        <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->getMaskedPassword() }}
                        </p>
                    </div>
                    @if ($this->hasPassword())
                        <x-filament::badge color="success" icon="heroicon-m-check">Défini</x-filament::badge>
                    @else
                        <x-filament::badge color="danger" icon="heroicon-m-x-mark">Manquant</x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Services --}}
        <x-filament::section>
            <x-slot name="heading">Services activés</x-slot>
            <x-slot name="description">
                Produits Colissimo proposés au checkout. Modifiez via <code>config/colissimo.php</code>.
            </x-slot>

            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($this->getServices() as $service)
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $service['label'] }}
                            </p>
                            <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                                Code : {{ $service['code'] }}
                            </p>
                        </div>
                        @if ($service['enabled'])
                            <x-filament::badge color="success">Activé</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Désactivé</x-filament::badge>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Grid --}}
        <x-filament::section>
            <x-slot name="heading">Grille tarifaire</x-slot>
            <x-slot name="description">
                Paliers de poids (France métropolitaine).
            </x-slot>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="py-2">Poids max</th>
                        <th class="py-2 text-right">Tarif HT</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->getGrid() as $bracket)
                        <tr>
                            <td class="py-2">≤ {{ $bracket['max_kg'] }} kg</td>
                            <td class="py-2 text-right font-mono">{{ $this->formatCents((int) $bracket['price']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        {{-- Shipper --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Adresse expéditeur</x-slot>
            <x-slot name="description">Variables <code>MDE_SHIPPER_*</code> dans <code>.env</code>.</x-slot>

            @php($shipper = $this->getShipper())
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase text-gray-500">Nom</dt>
                    <dd class="mt-0.5 font-mono">{{ $shipper['name'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Adresse</dt>
                    <dd class="mt-0.5 font-mono">{{ $shipper['street'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">CP / Ville</dt>
                    <dd class="mt-0.5 font-mono">{{ ($shipper['zip'] ?? '—').' '.($shipper['city'] ?? '') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Téléphone</dt>
                    <dd class="mt-0.5 font-mono">{{ $shipper['phone'] ?? '—' }}</dd>
                </div>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
