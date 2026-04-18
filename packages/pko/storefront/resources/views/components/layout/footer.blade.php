@php
$contact = config('storefront.contact');
$social = config('storefront.social');
@endphp

<footer class="bg-neutral-900 text-neutral-300 mt-16">
    <x-layout.usps />

    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            {{-- About --}}
            <div>
                <h3 class="text-white font-bold uppercase tracking-wider text-sm mb-4">À propos</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/pages/qui-sommes-nous" class="hover:text-white transition">Qui sommes-nous ?</a></li>
                    <li><a href="/pages/notre-metier" class="hover:text-white transition">Notre métier</a></li>
                    <li><a href="/pages/engagements-rse" class="hover:text-white transition">Engagements RSE</a></li>
                    <li><a href="/pages/recrutement" class="hover:text-white transition">Recrutement</a></li>
                </ul>
            </div>

            {{-- Informations --}}
            <div>
                <h3 class="text-white font-bold uppercase tracking-wider text-sm mb-4">Informations</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/actualites" class="hover:text-white transition">Actualités</a></li>
                    <li><a href="/magasins" class="hover:text-white transition">Nos magasins</a></li>
                    <li><a href="/pages/nos-marques" class="hover:text-white transition">Nos marques</a></li>
                    <li><a href="/pages/offres-du-moment" class="hover:text-white transition">Offres du moment</a></li>
                </ul>
            </div>

            {{-- Aide --}}
            <div>
                <h3 class="text-white font-bold uppercase tracking-wider text-sm mb-4">Besoin d'aide ?</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/pages/faq" class="hover:text-white transition">FAQ</a></li>
                    <li><a href="/pages/livraison" class="hover:text-white transition">Livraison</a></li>
                    <li><a href="/pages/retour-colis" class="hover:text-white transition">Retour colis</a></li>
                    <li><a href="/pages/nous-contacter" class="hover:text-white transition">Nous contacter</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div>
                <h3 class="text-white font-bold uppercase tracking-wider text-sm mb-4">Besoin d'un conseil ?</h3>
                <a href="tel:{{ preg_replace('/\s/', '', $contact['phone']) }}" class="block text-2xl font-black text-white mb-3 hover:text-primary-300 transition">
                    {{ $contact['phone'] }}
                </a>
                <a href="mailto:{{ $contact['email'] }}" class="inline-flex items-center gap-2 text-sm hover:text-white transition mb-6">
                    <x-ui.icon name="phone" class="w-4 h-4" />
                    {{ $contact['email'] }}
                </a>

                <div class="flex items-center gap-3">
                    @foreach (['facebook', 'instagram', 'linkedin', 'youtube'] as $net)
                        @if (! empty($social[$net]))
                            <a href="{{ $social[$net] }}" target="_blank" rel="noopener" class="w-9 h-9 rounded-full border border-neutral-700 flex items-center justify-center hover:bg-primary-600 hover:border-primary-600 transition" aria-label="{{ $net }}">
                                <x-ui.icon :name="$net" class="w-4 h-4" />
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Newsletter --}}
        <div class="mt-10 pt-8 border-t border-neutral-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-white font-semibold">Newsletter {{ brand_name() }}</p>
                <p class="text-sm text-neutral-400">Offres, nouveautés et actualités pros — sans spam, promis.</p>
            </div>
            <form method="POST" action="/newsletter" class="flex gap-2 md:w-96">
                @csrf
                <input type="email" name="email" required placeholder="Votre adresse e-mail" class="flex-1 rounded-md border-neutral-700 bg-neutral-800 text-white placeholder:text-neutral-500 text-sm focus:ring-primary-500 focus:border-primary-500" />
                <x-ui.button type="submit" variant="primary">S'abonner</x-ui.button>
            </form>
        </div>

        {{-- Legal --}}
        <div class="mt-10 pt-6 border-t border-neutral-800 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-neutral-500">
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
