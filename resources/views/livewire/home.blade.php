@php
use Pko\CustomerAuth\Support\ProAccess;
use Pko\Storefront\Support\StorefrontLinks;

// FEAT-009 : carrousel → schéma des solutions → sélection pros → actualités →
// bandeau compte pro. Tous les liens éditoriaux viennent de Storefront →
// Paramètres / Page d'accueil (config('storefront.home.*')) ; un lien vide masque
// l'élément.
$services = StorefrontLinks::visible(config('storefront.home.services', []), 'title');
$selection = StorefrontLinks::visible(config('storefront.home.selection', []), 'title');
$communityUrl = StorefrontLinks::safeHref(config('storefront.home.community_url'));

$user = auth()->user();
$isActivePro = ProAccess::isActivePro($user);
$pendingReason = ($user && ! $isActivePro) ? ProAccess::denialReason($user) : null;
@endphp

<div>
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        @livewire('storefront-cms.home-hero')
    </div>

    @livewire('storefront-cms.home-solutions')

    {{-- Raccourcis services (remplacement, identification de pièce, devis projet) --}}
    @if ($services !== [])
        <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 pb-6" aria-label="Accès aux services">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <a
                        href="{{ $service['href'] }}"
                        @if (StorefrontLinks::isInternal($service['href'])) wire:navigate @endif
                        class="group flex items-center gap-4 px-5 py-5 rounded-xl bg-white border border-neutral-200 hover:border-primary-200 hover:shadow-md transition"
                    >
                        <span class="w-12 h-12 rounded-full bg-accent-50 flex items-center justify-center text-primary-600 shrink-0">
                            <x-ui.icon :name="$service['icon'] ?? 'arrow-right'" class="w-5 h-5" />
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-semibold text-neutral-900 leading-snug">{{ $service['title'] }}</span>
                            @if (! empty($service['subtitle']))
                                <span class="block text-sm text-neutral-500 mt-1">{{ $service['subtitle'] }}</span>
                            @endif
                        </span>
                        <x-ui.icon name="arrow-up-right" class="w-5 h-5 text-primary-600 shrink-0 self-start transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- La sélection pour les pros : 3 raccourcis éditables --}}
    @if ($selection !== [])
        <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6" aria-labelledby="home-selection-title">
            <div class="mb-6">
                <div class="text-xs font-semibold uppercase tracking-[0.08em] text-accent-700 mb-1.5">À découvrir chez {{ brand_name() }}</div>
                <h2 id="home-selection-title" class="font-display font-bold text-3xl text-neutral-900">La sélection pour les pros</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($selection as $card)
                    @php
                        // Fonds alternés de la maquette, transposés en tokens DS.
                        $tone = ['bg-neutral-100', 'bg-primary-50', 'bg-accent-50'][$loop->index % 3];
                    @endphp
                    <a
                        href="{{ $card['href'] }}"
                        @if (StorefrontLinks::isInternal($card['href'])) wire:navigate @endif
                        class="group flex items-center gap-4 min-h-[7rem] px-6 py-6 rounded-xl {{ $tone }} border border-transparent hover:border-primary-200 hover:shadow-md transition"
                    >
                        <span class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-primary-600 shrink-0 shadow-sm">
                            <x-ui.icon :name="$card['icon'] ?? 'tag'" class="w-6 h-6" />
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-display font-bold text-lg text-neutral-900">{{ $card['title'] }}</span>
                            @if (! empty($card['subtitle']))
                                <span class="block text-sm text-neutral-500 mt-0.5">{{ $card['subtitle'] }}</span>
                            @endif
                        </span>
                        <x-ui.icon name="arrow-right" class="w-5 h-5 text-primary-600 shrink-0 transition group-hover:translate-x-1" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

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

    {{-- Bandeau espace professionnel (forest), affiché à tous : le CTA s'adapte
         à l'état du compte (cf. ProAccess, source unique). C'est le seul CTA lime
         de la page. --}}
    <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 pt-4" aria-labelledby="home-pro-title">
        <div class="relative overflow-hidden rounded-2xl bg-primary-600 text-white px-6 py-8 sm:px-10 sm:py-10">
            <span class="wk-decor wk-decor--br wk-decor--on-dark" style="--wk-decor-size: 340px; --wk-decor-opacity: 0.12;"></span>

            <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(16rem,1fr)] lg:items-center">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.08em] text-accent-300 mb-2">Votre espace professionnel</div>
                    <h2 id="home-pro-title" class="font-display font-bold text-3xl sm:text-4xl leading-tight">
                        Vos conditions pro.<br>Votre compte {{ brand_name() }}.
                    </h2>
                    <p class="mt-4 max-w-xl text-primary-100 leading-relaxed">
                        Créez votre compte. Après validation par notre équipe, accédez à vos tarifs et passez vos commandes.
                    </p>
                    <ol class="mt-6 flex flex-wrap items-center gap-2 text-xs font-medium text-primary-100">
                        @foreach (['Inscription', 'Validation '.brand_name(), 'Accès professionnel'] as $step)
                            @if (! $loop->first)
                                <li aria-hidden="true"><x-ui.icon name="chevron-right" class="w-3.5 h-3.5 text-primary-300" /></li>
                            @endif
                            <li class="px-3 py-1.5 rounded-full bg-white/10">
                                <span class="font-mono text-accent-300">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span> · {{ $step }}
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="flex flex-col items-start lg:items-center gap-3">
                    @if ($isActivePro)
                        <x-ui.button variant="accent" size="lg" href="/compte" iconRight="arrow-right">Accéder à mon espace pro</x-ui.button>
                        <a href="/compte/commandes" class="text-sm text-white/90 underline underline-offset-4 hover:text-accent-300 transition">Voir mes commandes</a>
                    @elseif ($user)
                        <div class="flex items-start gap-3 max-w-sm rounded-xl bg-white/10 px-4 py-3 text-sm text-primary-50">
                            <x-ui.icon name="clock" class="w-5 h-5 text-accent-300 shrink-0 mt-0.5" />
                            <span>{{ $pendingReason }}</span>
                        </div>
                    @else
                        <x-ui.button variant="accent" size="lg" href="/inscription" iconRight="arrow-right">Ouvrir un compte pro</x-ui.button>
                        <a href="/connexion" class="text-sm text-white/90 underline underline-offset-4 hover:text-accent-300 transition">Déjà inscrit ? Se connecter</a>
                    @endif
                </div>
            </div>

            @if ($communityUrl)
                <div class="relative mt-8 pt-6 border-t border-white/15 flex flex-wrap items-center gap-4">
                    <x-ui.icon name="messages-square" class="w-6 h-6 text-accent-300 shrink-0" />
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-semibold">La communauté {{ brand_name() }}</p>
                        <p class="text-sm text-primary-100">Entraide, conseils et retours d'expérience entre professionnels.</p>
                    </div>
                    <a href="{{ $communityUrl }}" class="inline-flex items-center gap-2 text-sm font-semibold hover:text-accent-300 transition">
                        Découvrir <x-ui.icon name="arrow-up-right" class="w-4 h-4" />
                    </a>
                </div>
            @endif
        </div>
    </section>
</div>
