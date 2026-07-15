<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Activation + clés --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit">
                    Enregistrer
                </x-filament::button>
            </div>
        </form>

        {{-- État de la configuration --}}
        <x-filament::section>
            <x-slot name="heading">État de la configuration</x-slot>
            <x-slot name="description">
                Vérification du numéro SIRET des professionnels via l'API INSEE Sirene (auth OAuth2 client_credentials).
            </x-slot>

            <div class="space-y-4">
                {{-- Activation --}}
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Vérification SIRET</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Contrôle automatique à l'inscription des comptes professionnels.
                        </p>
                    </div>
                    @if ($this->isEnabled())
                        <x-filament::badge color="success" icon="heroicon-m-check">Activée</x-filament::badge>
                    @else
                        <x-filament::badge color="gray" icon="heroicon-m-pause">Désactivée</x-filament::badge>
                    @endif
                </div>

                @if ($this->isEnabled() && ! $this->isFullyConfigured())
                    <div class="flex items-start gap-3 rounded-lg bg-warning-50 p-4 dark:bg-warning-500/10">
                        <x-heroicon-o-exclamation-triangle class="h-6 w-6 flex-shrink-0 text-warning-600 dark:text-warning-400" />
                        <div>
                            <p class="font-semibold text-warning-700 dark:text-warning-300">Vérification activée mais clés incomplètes</p>
                            <p class="mt-1 text-sm text-warning-700/80 dark:text-warning-300/80">
                                Sans consumer key/secret valides, les inscriptions restent en statut « en attente » (aucun contrôle réel).
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Clé API --}}
        <x-filament::section>
            <x-slot name="heading">Clé API INSEE</x-slot>
            <x-slot name="description">
                Source actuelle : <strong>{{ $this->getCurrentSource() === 'db' ? 'base de données (chiffré)' : '.env' }}</strong>.
                La clé est masquée. Le portail INSEE fournit une <strong>clé API unique</strong> (en-tête HTTP, sans OAuth).
            </x-slot>

            <div class="divide-y divide-gray-100 dark:divide-white/5">
                {{-- Clé API --}}
                <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Clé API (<code>INSEE_API_KEY</code>)
                        </p>
                        <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->getMaskedSecret($this->getApiKey()) }}
                        </p>
                    </div>
                    @if ($this->hasApiKey())
                        <x-filament::badge color="success" icon="heroicon-m-check">Définie</x-filament::badge>
                    @else
                        <x-filament::badge color="danger" icon="heroicon-m-x-mark">Manquante</x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Variables d'environnement --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Variables d'environnement (mode .env)</x-slot>
            <x-slot name="description">
                Utilisées quand la source est « .env ». À ajouter dans le fichier <code>.env</code>, puis vider le cache de config.
            </x-slot>

            <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed text-gray-100"># Vérification SIRET (INSEE Sirene 3.11 — clé API unique)
INSEE_ENABLED=true
INSEE_API_KEY=<clé API du portail INSEE></pre>

            <div class="mt-4 flex items-center gap-2 text-sm">
                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                <a href="https://portail-api.insee.fr/" target="_blank" rel="noopener"
                   class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                    Portail API INSEE (créer une application / obtenir les clés)
                </a>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
