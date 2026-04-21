<x-layout.storefront>
    <section class="py-8 md:py-12">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[['label' => $postType->label]]" class="mb-4" />
            <h1 class="text-3xl md:text-4xl font-black text-neutral-900 mb-8">{{ $postType->label }}</h1>

            @if ($posts->isEmpty())
                <p class="text-neutral-500 text-center py-12">Aucun contenu publié pour le moment.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($posts as $post)
                        <a href="{{ url('/'.$postType->url_segment.'/'.$post->slug) }}" class="group block bg-white border border-neutral-200 rounded-lg overflow-hidden hover:shadow-md transition">
                            @if ($post->cover_url)
                                <div class="aspect-[16/9] bg-neutral-100">
                                    <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="w-full h-full object-cover" />
                                </div>
                            @endif
                            <div class="p-5">
                                @if ($post->published_at)
                                    <p class="text-xs text-neutral-500">{{ $post->published_at->format('d/m/Y') }}</p>
                                @endif
                                <h3 class="font-bold text-neutral-900 text-lg group-hover:text-primary-700 mt-1 line-clamp-2">{{ $post->title }}</h3>
                                @if ($post->excerpt)
                                    <p class="text-sm text-neutral-600 mt-2 line-clamp-3">{{ $post->excerpt }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-10">{{ $posts->links() }}</div>
            @endif
        </div>
    </section>
</x-layout.storefront>
