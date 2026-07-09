<div>
    @if ($tiles->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($tiles as $tile)
                <a href="{{ $tile->cta_url ?? '#' }}" class="group relative overflow-hidden rounded-xl bg-primary-800 text-white aspect-[4/3] block transition duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                    {{-- Photo en fond --}}
                    @if ($tile->image_url)
                        <img src="{{ $tile->image_url }}" alt="" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                    @endif
                    {{-- Pastille forest en quart-de-cercle, coin haut-droite : porte le texte --}}
                    <div class="pointer-events-none absolute top-0 right-0 w-[155%] aspect-square rounded-full bg-primary-800 translate-x-1/2 -translate-y-1/2"></div>
                    {{-- Anneaux lime pleine couleur, centre pile dans l'angle bas-gauche --}}
                    <span class="wk-decor wk-decor--bl wk-decor--lime" style="--wk-decor-size: 200px;"></span>
                    <div class="relative z-10 p-5 flex flex-col items-end text-right">
                        <h3 class="font-display font-bold text-lg leading-tight">{{ $tile->title }}</h3>
                        @if ($tile->subtitle)<p class="text-xs text-white/90 mt-1">{{ $tile->subtitle }}</p>@endif
                        @if ($tile->cta_label)
                            <span class="mt-3 text-sm font-semibold text-accent-300 inline-flex items-center gap-1.5 group-hover:gap-2.5 transition-all">{{ $tile->cta_label }} <x-ui.icon name="arrow-right" class="w-4 h-4" /></span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
