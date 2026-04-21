<x-layout.storefront>
    <article class="py-8 md:py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[
                ['label' => $postType->label, 'url' => url('/'.$postType->url_segment)],
                ['label' => $post->title],
            ]" class="mb-4" />

            <header class="mb-8">
                @if ($post->published_at)
                    <p class="text-sm text-neutral-500 mb-2">{{ $post->published_at->format('d F Y') }}</p>
                @endif
                <h1 class="text-3xl md:text-4xl font-black text-neutral-900 leading-tight">{{ $post->title }}</h1>
                @if ($post->excerpt)
                    <p class="mt-3 text-lg text-neutral-600 leading-relaxed">{{ $post->excerpt }}</p>
                @endif
            </header>

            @if ($post->cover_url)
                <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="w-full rounded-lg mb-8" />
            @endif

            <x-page-builder::render :content="$post->content" :fallback="$post->body" />
        </div>
    </article>
</x-layout.storefront>
