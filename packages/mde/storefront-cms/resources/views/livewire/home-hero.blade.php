<div x-data="{ current: 0, count: {{ $slides->count() }}, timer: null, start() { if (this.count < 2) return; this.timer = setInterval(() => this.next(), 6000); }, next() { this.current = (this.current + 1) % this.count; }, prev() { this.current = (this.current - 1 + this.count) % this.count; }, go(i) { this.current = i; clearInterval(this.timer); this.start(); } }" x-init="start()" class="relative overflow-hidden rounded-lg">
    @if ($slides->isEmpty())
        <div class="bg-gradient-to-r from-primary-800 to-primary-600 text-white px-8 py-20 text-center">
            <h2 class="text-4xl font-black mb-2">MDE Distribution</h2>
            <p class="text-lg text-primary-100">Votre partenaire pro du bâtiment</p>
        </div>
    @else
        <div class="relative h-80 md:h-96 lg:h-[28rem]">
            @foreach ($slides as $idx => $slide)
                <div x-show="current === {{ $idx }}" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0" style="background-color: {{ $slide->bg_color }}; color: {{ $slide->text_color }};">
                    @if ($slide->image_url)
                        <img src="{{ $slide->image_url }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-90" />
                        <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/30 to-transparent"></div>
                    @endif
                    <div class="relative max-w-screen-xl mx-auto h-full flex items-center px-6 md:px-12 z-10">
                        <div class="max-w-xl">
                            <h2 class="text-4xl md:text-5xl font-black leading-tight mb-3" style="color: {{ $slide->text_color }};">{{ $slide->title }}</h2>
                            @if ($slide->subtitle)
                                <p class="text-lg md:text-xl mb-6 opacity-90">{{ $slide->subtitle }}</p>
                            @endif
                            @if ($slide->cta_label && $slide->cta_url)
                                <a href="{{ $slide->cta_url }}" class="inline-flex items-center gap-2 bg-white text-primary-700 px-6 py-3 rounded-md font-bold hover:bg-primary-50 transition">
                                    {{ $slide->cta_label }}
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($slides->count() > 1)
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-20">
                @foreach ($slides as $idx => $slide)
                    <button type="button" @click="go({{ $idx }})" class="w-2.5 h-2.5 rounded-full transition" :class="current === {{ $idx }} ? 'bg-white w-8' : 'bg-white/50 hover:bg-white/75'"></button>
                @endforeach
            </div>
            <button type="button" @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/20 hover:bg-white/30 backdrop-blur rounded-full flex items-center justify-center text-white transition z-20"><x-ui.icon name="chevron-left" class="w-5 h-5" /></button>
            <button type="button" @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/20 hover:bg-white/30 backdrop-blur rounded-full flex items-center justify-center text-white transition z-20"><x-ui.icon name="chevron-right" class="w-5 h-5" /></button>
        @endif
    @endif
</div>
