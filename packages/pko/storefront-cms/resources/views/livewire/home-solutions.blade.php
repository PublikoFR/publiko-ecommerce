{{-- « À chaque installation, sa solution » (FEAT-009) — schéma + liste synchronisés.
     Desktop : survol/focus d'une ligne ou clic sur une pastille = sélection, la carte
     du bas porte le CTA « Voir la famille ». Mobile : liste seule (liens directs). --}}
<div>
    @if ($items !== [])
        <section
            class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12"
            aria-labelledby="home-solutions-title"
            x-data="{
                items: @js(collect($items)->keyBy('key')),
                active: @js($items[0]['key']),
                get current() { return this.items[this.active]; },
            }"
        >
            <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.08em] text-accent-700 mb-1.5">Vue d'ensemble de nos solutions</div>
                    <h2 id="home-solutions-title" class="font-display font-bold text-3xl text-neutral-900">À chaque installation, sa solution.</h2>
                </div>
                <p class="hidden md:block text-sm text-neutral-500 text-right">Sélectionnez une zone du bâtiment<br>ou une famille de produits.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-[minmax(0,1.7fr)_minmax(16rem,1fr)]">
                {{-- Schéma --}}
                <div class="hidden md:block relative rounded-2xl bg-neutral-100 border border-neutral-200 overflow-hidden">
                    <span class="absolute left-4 top-4 flex items-center gap-2 text-xs text-neutral-500">
                        <x-ui.icon name="pointer" class="w-3.5 h-3.5" /> Explorez nos univers
                    </span>
                    <svg
                        viewBox="{{ $viewBox }}"
                        class="block w-full h-auto"
                        fill="none"
                        stroke-width="1.4"
                        stroke-linejoin="round"
                        role="group"
                        aria-label="Schéma d'un site équipé : habitation et bâtiment professionnel"
                    >
                        @include('storefront-cms::livewire.partials.home-solutions-scene')

                        @foreach ($items as $item)
                            <g
                                role="button"
                                tabindex="0"
                                aria-label="{{ $item['number'] }} — {{ $item['label'] }}"
                                :aria-pressed="(active === @js($item['key'])).toString()"
                                class="cursor-pointer outline-none group"
                                @click="active = @js($item['key'])"
                                @mouseenter="active = @js($item['key'])"
                                @focus="active = @js($item['key'])"
                                @keydown.enter.prevent="window.Livewire.navigate(@js($item['url']))"
                            >
                                <line x1="{{ $item['x'] }}" y1="{{ $item['y'] }}" x2="{{ $item['x'] }}" y2="{{ $item['y'] - 14 }}" class="stroke-primary-600" stroke-width="1.5" />
                                <circle cx="{{ $item['x'] }}" cy="{{ $item['y'] }}" r="2.5" class="fill-primary-600 stroke-none" />
                                <circle
                                    cx="{{ $item['x'] }}" cy="{{ $item['y'] - 26 }}" r="18"
                                    class="fill-accent-500/30 stroke-none transition-opacity"
                                    :class="active === @js($item['key']) ? 'opacity-100' : 'opacity-0'"
                                />
                                <circle
                                    cx="{{ $item['x'] }}" cy="{{ $item['y'] - 26 }}" r="12.5"
                                    class="stroke-white transition-colors group-focus-visible:stroke-accent-500"
                                    stroke-width="3"
                                    :class="active === @js($item['key']) ? 'fill-accent-500' : 'fill-primary-600'"
                                />
                                <text
                                    x="{{ $item['x'] }}" y="{{ $item['y'] - 22 }}"
                                    text-anchor="middle"
                                    class="font-sans font-bold text-[11px] stroke-none"
                                    :class="active === @js($item['key']) ? 'fill-primary-700' : 'fill-white'"
                                >{{ $item['number'] }}</text>
                            </g>
                        @endforeach
                    </svg>
                </div>

                {{-- Liste des familles --}}
                <ol class="flex flex-col justify-between divide-y divide-neutral-200 border-y border-neutral-200 md:border-y-0" aria-label="Familles de produits">
                    @foreach ($items as $item)
                        <li>
                            <a
                                href="{{ $item['url'] }}"
                                wire:navigate
                                @mouseenter="active = @js($item['key'])"
                                @focus="active = @js($item['key'])"
                                class="flex items-center gap-3 px-3 py-3 md:py-2.5 rounded-md text-sm transition hover:text-primary-600"
                                :class="active === @js($item['key']) ? 'md:bg-accent-50 md:text-primary-700 md:font-semibold' : 'text-neutral-700'"
                            >
                                <span class="font-mono text-xs text-neutral-400 w-5 shrink-0">{{ $item['number'] }}</span>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                <x-ui.icon name="chevron-right" class="w-4 h-4 text-neutral-400 shrink-0" />
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Famille sélectionnée (desktop) --}}
            <div class="hidden md:flex items-center justify-between gap-6 mt-5 pb-5 border-b border-neutral-200">
                <div class="flex items-center gap-4 min-w-0">
                    <span class="font-display font-bold text-3xl text-accent-600" x-text="current.number">{{ $items[0]['number'] }}</span>
                    <div class="min-w-0">
                        <p class="font-display font-bold text-lg text-neutral-900" x-text="current.label">{{ $items[0]['label'] }}</p>
                        <p class="text-sm text-neutral-500" x-text="current.description">{{ $items[0]['description'] }}</p>
                    </div>
                </div>
                <a
                    :href="current.url"
                    href="{{ $items[0]['url'] }}"
                    wire:navigate
                    class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-md text-sm font-semibold bg-primary-600 text-white hover:bg-primary-700 shadow-sm transition shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                >
                    Voir la famille <x-ui.icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>
        </section>
    @endif
</div>
