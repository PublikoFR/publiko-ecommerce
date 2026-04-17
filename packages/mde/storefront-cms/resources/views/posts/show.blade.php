<x-layout.storefront>
    <article class="py-8 md:py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[['label' => 'Actualités', 'url' => route('posts.index')], ['label' => $post->title]]" class="mb-4" />

            <header class="mb-8">
                <p class="text-sm text-neutral-500 mb-2">{{ optional($post->published_at)->format('d F Y') }}</p>
                <h1 class="text-3xl md:text-4xl font-black text-neutral-900 leading-tight">{{ $post->title }}</h1>
                @if ($post->excerpt)<p class="mt-3 text-lg text-neutral-600 leading-relaxed">{{ $post->excerpt }}</p>@endif
            </header>

            @if ($post->cover_url)
                <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="w-full rounded-lg mb-8" />
            @endif

            <div class="prose max-w-none prose-neutral prose-lg prose-headings:font-black prose-headings:text-neutral-900">
                {!! $post->body !!}
            </div>
        </div>
    </article>
</x-layout.storefront>
