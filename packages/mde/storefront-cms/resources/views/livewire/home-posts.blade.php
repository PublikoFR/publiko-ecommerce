<div>
    @if ($posts->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach ($posts as $post)
                <a href="{{ route('posts.show', $post->slug) }}" class="group block bg-white border border-neutral-200 rounded-lg overflow-hidden hover:shadow-md transition">
                    @if ($post->cover_url)
                        <div class="aspect-[16/9] bg-neutral-100">
                            <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                        </div>
                    @endif
                    <div class="p-4">
                        <p class="text-xs text-neutral-500">{{ optional($post->published_at)->format('d/m/Y') }}</p>
                        <h3 class="font-bold text-neutral-900 group-hover:text-primary-700 mt-1 line-clamp-2">{{ $post->title }}</h3>
                        @if ($post->excerpt)<p class="text-sm text-neutral-600 mt-2 line-clamp-3">{{ $post->excerpt }}</p>@endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
