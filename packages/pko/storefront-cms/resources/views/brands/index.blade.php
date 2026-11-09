{{--
    Liste publique des marques (/marques). Uniquement les marques ayant au
    moins un produit publié. Index alphabétique au-delà de
    BrandController::ALPHA_INDEX_THRESHOLD marques ($groups non null).
--}}
@php
    $metaDescription = 'Découvrez toutes les marques disponibles chez '.brand_name().'.';
@endphp

<x-layout.storefront :title="'Nos marques — '.brand_name()">
    @push('head')
        <meta name="description" content="{{ $metaDescription }}">
    @endpush

    <section class="py-8 md:py-12">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[['label' => 'Nos marques']]" class="mb-4" />

            <header class="mb-8">
                <h1 class="text-3xl md:text-4xl font-display font-bold text-neutral-900 leading-tight">Nos marques</h1>
            </header>

            @if ($items->isEmpty())
                <p class="text-neutral-500 text-center py-12">Aucune marque disponible pour le moment.</p>
            @elseif ($groups)
                <nav aria-label="Index alphabétique" class="mb-8 flex flex-wrap gap-1.5">
                    @foreach ($groups->keys() as $letter)
                        <a href="#marques-{{ $letter === '#' ? 'autres' : $letter }}" class="inline-flex items-center justify-center min-w-9 h-9 px-2 rounded-md border border-neutral-200 bg-white text-sm font-semibold text-neutral-700 hover:border-primary-500 hover:text-primary-700 transition">{{ $letter }}</a>
                    @endforeach
                </nav>

                <div class="space-y-10">
                    @foreach ($groups as $letter => $groupItems)
                        <section id="marques-{{ $letter === '#' ? 'autres' : $letter }}" class="scroll-mt-24">
                            <h2 class="text-xl font-display font-bold text-primary-700 mb-4">{{ $letter }}</h2>
                            @include('storefront-cms::brands.partials.grid', ['items' => $groupItems])
                        </section>
                    @endforeach
                </div>
            @else
                @include('storefront-cms::brands.partials.grid', ['items' => $items])
            @endif
        </div>
    </section>
</x-layout.storefront>
