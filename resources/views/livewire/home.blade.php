<div>
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        @livewire('storefront-cms.home-hero')
    </div>

    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @livewire('storefront-cms.home-tiles')
    </section>

    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex items-end justify-between mb-6">
            <h2 class="text-2xl md:text-3xl font-black text-neutral-900">Nouveautés & produits vedettes</h2>
            <a href="/collections/nouveautes" class="text-sm font-semibold text-primary-600 hover:text-primary-700" wire:navigate>Voir tout →</a>
        </div>
        @livewire('storefront-cms.home-featured')
    </section>

    <section class="bg-white border-y border-neutral-200 py-10">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl md:text-3xl font-black text-neutral-900 uppercase tracking-wide">Nos offres du moment</h2>
            </div>
            @livewire('storefront-cms.home-offers')
        </div>
    </section>

    <section class="bg-primary-50 py-14">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl md:text-3xl font-black text-primary-900 mb-4">MDE Distribution au service de votre métier</h2>
            <p class="text-neutral-700 leading-relaxed">
                Distributeur professionnel français, MDE accompagne les <strong>installateurs, artisans et entreprises du bâtiment</strong> avec une offre complète : <strong>portails, volets, automatismes, domotique, motorisations</strong> et accessoires. 60 000 références disponibles en 24 h, tarifs pros dégressifs, support dédié et réseau de proximité.
            </p>
            <div class="mt-6">
                <x-ui.button variant="primary" href="/pages/qui-sommes-nous" size="lg">Découvrir MDE →</x-ui.button>
            </div>
        </div>
    </section>

    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-6">
            <h2 class="text-2xl md:text-3xl font-black text-neutral-900">Actualités MDE</h2>
            <a href="/actualites" class="text-sm font-semibold text-primary-600 hover:text-primary-700" wire:navigate>Toutes les actualités →</a>
        </div>
        @livewire('storefront-cms.home-posts')
    </section>
</div>
