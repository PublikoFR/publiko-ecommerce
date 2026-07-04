<div>
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        @livewire('storefront-cms.home-hero')
    </div>

    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-6">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.08em] text-accent-700 mb-1.5">Catalogue</div>
                <h2 class="font-display font-bold text-3xl text-neutral-900">Nos univers</h2>
            </div>
        </div>
        @livewire('storefront-cms.home-tiles')
    </section>

    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
        <div class="flex items-end justify-between mb-6">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.08em] text-accent-700 mb-1.5">Sélection</div>
                <h2 class="font-display font-bold text-3xl text-neutral-900">Nouveautés &amp; produits vedettes</h2>
            </div>
            <a href="/collections#nouveautes" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 hover:text-primary-700" wire:navigate>
                Voir tout <x-ui.icon name="arrow-right" class="w-4 h-4" />
            </a>
        </div>
        @livewire('storefront-cms.home-featured')
    </section>

    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-8">
            <div class="text-xs font-semibold uppercase tracking-[0.08em] text-accent-700 mb-1.5">Bons plans</div>
            <h2 class="font-display font-bold text-3xl text-neutral-900">Nos offres du moment</h2>
        </div>
        @livewire('storefront-cms.home-offers')
    </section>

    {{-- Bandeau compte pro (Design System : split lime / forest) --}}
    @guest
    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid md:grid-cols-2 rounded-2xl overflow-hidden border border-neutral-200">
            <div class="relative bg-accent-500 p-10 flex flex-col justify-center overflow-hidden">
                <span class="wk-decor wk-decor--br" style="--wk-decor-color: var(--forest-600); --wk-decor-opacity: 0.14; --wk-decor-size: 320px;"></span>
                <div class="relative font-display font-bold text-3xl text-primary-700 leading-tight">Ouvrez votre compte pro</div>
                <p class="relative text-primary-700/90 mt-3 mb-6 max-w-sm leading-relaxed">Tarifs dégressifs, encours dédié, devis rapides et un interlocuteur unique pour vos chantiers.</p>
                <div class="relative">
                    <x-ui.button variant="primary" size="lg" href="/inscription" iconRight="arrow-right">Créer mon compte</x-ui.button>
                </div>
            </div>
            <div class="relative bg-primary-600 p-10 text-white flex flex-col justify-center gap-4 overflow-hidden">
                <span class="wk-decor wk-decor--tr wk-decor--on-dark" style="--wk-decor-size: 300px;"></span>
                @foreach ([['percent', 'Tarifs pro dégressifs par volume'], ['document', 'Devis chiffré rapide'], ['credit-card', 'Paiement à 30 / 45 jours fin de mois']] as [$ic, $txt])
                    <div class="relative flex items-center gap-3">
                        <x-ui.icon :name="$ic" class="w-6 h-6 text-accent-400" />
                        <span>{{ $txt }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endguest

    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-6">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.08em] text-accent-700 mb-1.5">Le blog</div>
                <h2 class="font-display font-bold text-3xl text-neutral-900">Actualités</h2>
            </div>
            <a href="/actualites" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 hover:text-primary-700" wire:navigate>
                Toutes les actualités <x-ui.icon name="arrow-right" class="w-4 h-4" />
            </a>
        </div>
        @livewire('storefront-cms.home-posts')
    </section>
</div>
