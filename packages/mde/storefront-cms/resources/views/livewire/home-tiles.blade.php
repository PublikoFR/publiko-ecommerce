<div>
    @if ($tiles->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($tiles as $tile)
                <a href="{{ $tile->cta_url ?? '#' }}" class="group relative overflow-hidden rounded-lg bg-primary-600 text-white aspect-[4/3] block">
                    @if ($tile->image_url)
                        <img src="{{ $tile->image_url }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-70 group-hover:opacity-80 transition" />
                        <div class="absolute inset-0 bg-gradient-to-br from-primary-900/80 to-primary-600/40"></div>
                    @endif
                    <div class="relative z-10 p-4 h-full flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-lg leading-tight">{{ $tile->title }}</h3>
                            @if ($tile->subtitle)<p class="text-xs opacity-90 mt-1">{{ $tile->subtitle }}</p>@endif
                        </div>
                        @if ($tile->cta_label)
                            <span class="text-xs font-bold uppercase tracking-wider inline-flex items-center gap-1 group-hover:gap-2 transition-all">{{ $tile->cta_label }} →</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
