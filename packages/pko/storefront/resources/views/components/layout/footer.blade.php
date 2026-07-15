@php
$contact = config('storefront.contact');
$social = config('storefront.social');
@endphp

<footer class="bg-primary-700 text-neutral-300 mt-16">
    <x-layout.usps />

    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            {{-- About --}}
            <div>
                <h3 class="text-white font-display font-bold text-[15px] mb-4">À propos</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/pages/qui-sommes-nous" class="hover:text-white transition">Qui sommes-nous ?</a></li>
                    <li><a href="/pages/notre-metier" class="hover:text-white transition">Notre métier</a></li>
                    <li><a href="/pages/engagements-rse" class="hover:text-white transition">Engagements RSE</a></li>
                    <li><a href="/pages/recrutement" class="hover:text-white transition">Recrutement</a></li>
                </ul>
            </div>

            {{-- Informations --}}
            <div>
                <h3 class="text-white font-display font-bold text-[15px] mb-4">Informations</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/actualites" class="hover:text-white transition">Actualités</a></li>
                    <li><a href="/magasins" class="hover:text-white transition">Nos magasins</a></li>
                    <li><a href="/pages/nos-marques" class="hover:text-white transition">Nos marques</a></li>
                    <li><a href="/pages/offres-du-moment" class="hover:text-white transition">Offres du moment</a></li>
                </ul>
            </div>

            {{-- Aide --}}
            <div>
                <h3 class="text-white font-display font-bold text-[15px] mb-4">Besoin d'aide ?</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/pages/faq" class="hover:text-white transition">FAQ</a></li>
                    <li><a href="/pages/livraison" class="hover:text-white transition">Livraison</a></li>
                    <li><a href="/pages/retour-colis" class="hover:text-white transition">Retour colis</a></li>
                    <li><a href="/contact" class="hover:text-white transition" wire:navigate>Nous contacter</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div>
                <h3 class="text-white font-display font-bold text-[15px] mb-4">Besoin d'un conseil ?</h3>
                <a href="tel:{{ preg_replace('/\s/', '', $contact['phone']) }}" class="block text-2xl font-display font-bold text-white mb-3 hover:text-accent-300 transition">
                    {{ $contact['phone'] }}
                </a>
                <a href="mailto:{{ $contact['email'] }}" class="inline-flex items-center gap-2 text-sm hover:text-white transition mb-6">
                    <x-ui.icon name="phone" class="w-4 h-4" />
                    {{ $contact['email'] }}
                </a>

                <div class="flex items-center gap-3">
                    @foreach (['facebook', 'instagram', 'linkedin', 'youtube'] as $net)
                        @if (! empty($social[$net]))
                            <a href="{{ $social[$net] }}" target="_blank" rel="noopener" class="w-9 h-9 rounded-full border border-white/20 flex items-center justify-center hover:bg-accent-500 hover:border-accent-500 hover:text-primary-700 transition" aria-label="{{ $net }}">
                                <x-ui.icon :name="$net" class="w-4 h-4" />
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Newsletter --}}
        <div class="mt-10 pt-8 border-t border-white/10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-white font-semibold">Newsletter {{ brand_name() }}</p>
                <p class="text-sm text-neutral-400">Offres, nouveautés et actualités pros — sans spam, promis.</p>
            </div>
            <form method="POST" action="/newsletter" class="flex gap-2 md:w-96">
                @csrf
                <input type="email" name="email" required placeholder="Votre adresse e-mail" class="flex-1 rounded-md border-white/20 bg-white/10 text-white placeholder:text-neutral-400 text-sm focus:ring-accent-500 focus:border-accent-500" />
                <x-ui.button type="submit" variant="primary">S'abonner</x-ui.button>
            </form>
        </div>

        {{-- Legal --}}
        <div class="mt-10 pt-6 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-neutral-500">
            <div>© {{ now()->year }} {{ brand_name() }}. Tous droits réservés.</div>
            <div class="flex items-center gap-4 flex-wrap justify-center">
                <a href="/pages/mentions-legales" class="hover:text-white transition">Mentions légales</a>
                <a href="/pages/cgv" class="hover:text-white transition">CGV</a>
                <a href="/pages/politique-cookies" class="hover:text-white transition">Cookies</a>
                <a href="/pages/politique-donnees" class="hover:text-white transition">Données personnelles</a>
            </div>
        </div>
    </div>
</footer>
