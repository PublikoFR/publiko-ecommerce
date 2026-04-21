<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Formulaire source + credentials --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit">
                    Enregistrer
                </x-filament::button>
            </div>
        </form>

        {{-- Status global --}}
        <x-filament::section>
            <x-slot name="heading">État de la configuration</x-slot>
            <x-slot name="description">
                Vue d'ensemble des clés API et du webhook Stripe requis pour traiter les paiements par carte.
            </x-slot>

            @if ($this->isFullyConfigured())
                <div class="flex items-start gap-3 rounded-lg bg-success-50 p-4 dark:bg-success-500/10">
                    <x-heroicon-o-check-circle class="h-6 w-6 flex-shrink-0 text-success-600 dark:text-success-400" />
                    <div>
                        <p class="font-semibold text-success-700 dark:text-success-300">
                            Stripe est configuré
                        </p>
                        <p class="mt-1 text-sm text-success-700/80 dark:text-success-300/80">
                            Les trois éléments requis sont présents. Lancez le test de connexion pour valider les clés.
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-3 rounded-lg bg-warning-50 p-4 dark:bg-warning-500/10">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 flex-shrink-0 text-warning-600 dark:text-warning-400" />
                    <div>
                        <p class="font-semibold text-warning-700 dark:text-warning-300">
                            Configuration incomplète
                        </p>
                        <p class="mt-1 text-sm text-warning-700/80 dark:text-warning-300/80">
                            Une ou plusieurs clés sont manquantes. Source actuelle :
                            <strong>{{ $this->getCurrentSource() === 'db' ? 'base de données' : 'fichier .env' }}</strong>.
                        </p>
                    </div>
                </div>
            @endif
        </x-filament::section>

        {{-- Clés --}}
        <x-filament::section>
            <x-slot name="heading">Clés API Stripe</x-slot>
            <x-slot name="description">
                Source actuelle : <strong>{{ $this->getCurrentSource() === 'db' ? 'base de données (chiffré)' : '.env' }}</strong>.
                Les clés secrètes sont masquées.
            </x-slot>

            <div class="divide-y divide-gray-100 dark:divide-white/5">
                {{-- Publishable key --}}
                <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Clé publique (<code>STRIPE_PK</code>)
                        </p>
                        <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->hasPublicKey() ? $this->getPublicKey() : 'Non renseignée' }}
                        </p>
                    </div>
                    @if ($this->hasPublicKey())
                        <x-filament::badge color="success" icon="heroicon-m-check">Définie</x-filament::badge>
                    @else
                        <x-filament::badge color="danger" icon="heroicon-m-x-mark">Manquante</x-filament::badge>
                    @endif
                </div>

                {{-- Secret key --}}
                <div class="flex items-center justify-between gap-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Clé secrète (<code>STRIPE_SECRET</code>)
                        </p>
                        <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->getMaskedSecret($this->getSecretKey()) }}
                        </p>
                    </div>
                    @if ($this->hasSecretKey())
                        <x-filament::badge color="success" icon="heroicon-m-check">Définie</x-filament::badge>
                    @else
                        <x-filament::badge color="danger" icon="heroicon-m-x-mark">Manquante</x-filament::badge>
                    @endif
                </div>

                {{-- Webhook secret --}}
                <div class="flex items-center justify-between gap-4 py-3 last:pb-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Secret webhook (<code>LUNAR_STRIPE_WEBHOOK_SECRET</code>)
                        </p>
                        <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->getMaskedSecret($this->getWebhookSecret()) }}
                        </p>
                    </div>
                    @if ($this->hasWebhookSecret())
                        <x-filament::badge color="success" icon="heroicon-m-check">Défini</x-filament::badge>
                    @else
                        <x-filament::badge color="danger" icon="heroicon-m-x-mark">Manquant</x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Webhook --}}
        <x-filament::section>
            <x-slot name="heading">Webhook Stripe</x-slot>
            <x-slot name="description">
                URL à renseigner dans le dashboard Stripe, section <em>Developers → Webhooks → Add endpoint</em>.
                Évènements requis : <code>payment_intent.*</code> et <code>charge.*</code>.
            </x-slot>

            <div class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Endpoint à copier
                    </p>
                    <div class="mt-1 flex items-center gap-2 rounded-lg bg-gray-50 p-3 font-mono text-sm text-gray-900 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-white dark:ring-white/10">
                        <x-heroicon-o-link class="h-4 w-4 flex-shrink-0 text-gray-400" />
                        <span class="break-all">{{ $this->getWebhookUrl() }}</span>
                    </div>
                </div>

                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    <li>Ajoutez l'endpoint ci-dessus dans votre dashboard Stripe.</li>
                    <li>Sélectionnez les évènements : <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>, <code>charge.refunded</code>.</li>
                    <li>Copiez le secret signé (format <code>whsec_...</code>) dans <code>LUNAR_STRIPE_WEBHOOK_SECRET</code>.</li>
                </ul>
            </div>
        </x-filament::section>

        {{-- Env vars --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Variables d'environnement à renseigner</x-slot>
            <x-slot name="description">
                À ajouter dans le fichier <code>.env</code> à la racine du projet, puis relancer les conteneurs.
            </x-slot>

            <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed text-gray-100"># Stripe (lunarphp/stripe)
STRIPE_PK=pk_test_...
STRIPE_SECRET=sk_test_...
LUNAR_STRIPE_WEBHOOK_SECRET=whsec_...</pre>

            <div class="mt-4 flex items-center gap-2 text-sm">
                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener"
                   class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                    Ouvrir le dashboard Stripe (clés API)
                </a>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
