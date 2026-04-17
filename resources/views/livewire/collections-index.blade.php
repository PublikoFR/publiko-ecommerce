<section class="py-8 md:py-12">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb :items="[['label' => 'Tout notre catalogue']]" class="mb-4" />
        <h1 class="text-3xl md:text-4xl font-black text-neutral-900 mb-2">Tout notre catalogue</h1>
        <p class="text-neutral-600 mb-8">Parcourez nos collections ou découvrez les dernières nouveautés.</p>

        <section class="mb-12">
            <h2 class="text-xl font-bold text-neutral-900 mb-4">Nos collections</h2>
            @if ($collections->isEmpty())
                <p class="text-neutral-500">Aucune collection pour le moment.</p>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    @foreach ($collections as $collection)
                        @php $slug = $collection->defaultUrl?->slug; @endphp
                        <a href="{{ $slug ? route('collection.view', $slug) : '#' }}" class="group block bg-white border border-neutral-200 rounded-lg p-5 hover:border-primary-300 hover:shadow-md transition text-center">
                            <div class="w-16 h-16 mx-auto mb-3 bg-primary-50 text-primary-600 rounded-full flex items-center justify-center">
                                <x-ui.icon name="shopping-bag" class="w-8 h-8" />
                            </div>
                            <h3 class="font-bold text-neutral-900 group-hover:text-primary-700 transition">{{ $collection->translateAttribute('name') }}</h3>
                            <p class="text-xs text-neutral-500 mt-1">{{ $collection->products()->count() }} produits</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="nouveautes">
            <div class="flex items-end justify-between mb-4">
                <h2 class="text-xl font-bold text-neutral-900">Nouveautés</h2>
                <a href="/recherche" class="text-sm font-semibold text-primary-600 hover:text-primary-700">Voir tous les produits →</a>
            </div>
            @if ($newArrivals->isEmpty())
                <p class="text-neutral-500">Aucune nouveauté pour le moment.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @foreach ($newArrivals as $product)
                        <x-storefront.product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</section>
