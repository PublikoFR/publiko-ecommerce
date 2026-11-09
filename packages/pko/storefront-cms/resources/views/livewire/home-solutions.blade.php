{{-- « À chaque installation, sa solution » (FEAT-009) — schéma + liste synchronisés.
     Les pastilles portent le picto de la famille (1re image de la catégorie, le même
     que le menu latéral) ; sans picto, la pastille retombe sur le numéro. La liste
     n'affiche que les intitulés (le picto y ferait double emploi avec le schéma).
     Desktop : survol/focus d'une ligne ou clic sur une pastille = sélection ; la tuile
     en bas à gauche du schéma présente la famille et mène à sa page.
     Mobile : liste seule (liens directs). --}}
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
                            @php
                                // Tuile 34×34 posée au-dessus de l'ancre, reliée par une tige.
                                $tx = $item['x'] - 17;
                                $ty = $item['y'] - $item['lift'] - 34;
                            @endphp
                            <g
                                role="button"
                                tabindex="0"
                                aria-label="{{ $item['label'] }}"
                                :aria-pressed="(active === @js($item['key'])).toString()"
                                class="cursor-pointer outline-none group"
                                @click="active = @js($item['key'])"
                                @mouseenter="active = @js($item['key'])"
                                @focus="active = @js($item['key'])"
                                @keydown.enter.prevent="window.Livewire.navigate(@js($item['url']))"
                            >
                                <line x1="{{ $item['x'] }}" y1="{{ $item['y'] }}" x2="{{ $item['x'] }}" y2="{{ $item['y'] - $item['lift'] }}" class="stroke-primary-600" stroke-width="1.5" />
                                <circle cx="{{ $item['x'] }}" cy="{{ $item['y'] }}" r="2.5" class="fill-primary-600 stroke-none" />
                                {{-- Sélection : fond blanc conservé, seul le contour passe en vert clair. --}}
                                <rect
                                    x="{{ $tx }}" y="{{ $ty }}" width="34" height="34" rx="8"
                                    class="fill-white transition-colors group-focus-visible:stroke-primary-600"
                                    :class="active === @js($item['key']) ? 'stroke-accent-500' : 'stroke-primary-200'"
                                    :stroke-width="active === @js($item['key']) ? 3 : 1.5"
                                    stroke-width="1.5"
                                />
                                @if ($item['icon'] !== '')
                                    <image href="{{ $item['icon'] }}" x="{{ $tx + 5 }}" y="{{ $ty + 5 }}" width="24" height="24" preserveAspectRatio="xMidYMid meet" />
                                @else
                                    <text
                                        x="{{ $item['x'] }}" y="{{ $ty + 21 }}"
                                        text-anchor="middle"
                                        class="font-sans font-bold text-[12px] fill-primary-700 stroke-none"
                                    >{{ $item['number'] }}</text>
                                @endif
                            </g>
                        @endforeach
                    </svg>

                    {{-- Famille sélectionnée : tuile cliquable dans le coin bas-gauche du schéma --}}
                    <a
                        :href="current.url"
                        href="{{ $items[0]['url'] }}"
                        wire:navigate
                        class="group/tile absolute left-4 bottom-4 flex items-center gap-4 max-w-[min(26rem,calc(100%-2rem))] rounded-xl bg-white/95 backdrop-blur-sm border border-neutral-200 shadow-md pl-3 pr-4 py-3 hover:border-primary-200 hover:shadow-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        <span class="w-12 h-12 flex items-center justify-center shrink-0">
                            <img :src="current.icon" @if ($items[0]['icon'] !== '') src="{{ $items[0]['icon'] }}" @endif alt="" width="36" height="36" class="w-9 h-9 object-contain" x-show="current.icon" @if ($items[0]['icon'] === '') style="display: none" @endif>
                            <span class="font-display font-bold text-lg text-primary-700" x-show="! current.icon" x-text="current.number" @if ($items[0]['icon'] !== '') style="display: none" @endif>{{ $items[0]['number'] }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-display font-bold text-neutral-900 leading-snug" x-text="current.label">{{ $items[0]['label'] }}</span>
                            <span class="block text-sm text-neutral-500 leading-snug mt-0.5" x-text="current.description">{{ $items[0]['description'] }}</span>
                        </span>
                        <x-ui.icon name="arrow-right" class="w-5 h-5 text-primary-600 shrink-0 transition group-hover/tile:translate-x-1" />
                        <span class="sr-only">Voir la famille</span>
                    </a>
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
                                <span class="flex-1">{{ $item['label'] }}</span>
                                <x-ui.icon name="chevron-right" class="w-4 h-4 text-neutral-400 shrink-0" />
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>

        </section>
    @endif
</div>
