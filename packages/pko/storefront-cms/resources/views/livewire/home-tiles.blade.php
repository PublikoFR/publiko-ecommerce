<div>
    @if ($tiles->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($tiles as $tile)
                <a href="{{ $tile->cta_url ?? '#' }}" class="group relative overflow-hidden rounded-xl bg-primary-600 text-white aspect-[4/3] block transition duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                    @if ($tile->image_url)
                        <img src="{{ $tile->image_url }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-70 group-hover:opacity-80 group-hover:scale-105 transition duration-300" />
                        <div class="absolute inset-0 bg-gradient-to-br from-primary-900/80 to-primary-600/40"></div>
                    @else
                        <span class="wk-decor wk-decor--br wk-decor--on-dark" style="--wk-decor-size: 220px;"></span>
                    @endif
                    <div class="relative z-10 p-5 h-full flex flex-col justify-between">
                        <div>
                            <h3 class="font-display font-bold text-lg leading-tight">{{ $tile->title }}</h3>
                            @if ($tile->subtitle)<p class="text-xs text-white/90 mt-1">{{ $tile->subtitle }}</p>@endif
                        </div>
                        @if ($tile->cta_label)
                            <span class="text-sm font-semibold text-accent-300 inline-flex items-center gap-1.5 group-hover:gap-2.5 transition-all">{{ $tile->cta_label }} <x-ui.icon name="arrow-right" class="w-4 h-4" /></span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
