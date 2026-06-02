{{--
    OVERRIDE PKO (prototype layout A) — fork de
    vendor/filament/filament/resources/views/components/sidebar/item.blade.php

    But : rendre les items à `childItems` comme un sous-menu DROPDOWN ANIMÉ
    (toggle au clic, x-collapse) tout en restant imbriqué dans son groupe,
    au lieu du comportement natif (enfants révélés seulement si actif).

    ⚠️ FORK d'une vue interne Filament SANS garantie de stabilité. À re-diff
    contre l'upstream à CHAQUE montée de version Filament. Seul ajout vs natif :
    le bloc `@if ($hasChildItems)` (bouton toggle + chevron + <ul> x-collapse).
    Les items SANS enfants conservent strictement le rendu natif (branche @else).
--}}
@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $hasChildItems = filled($childItems);
@endphp

<li
    @if ($hasChildItems)
        x-data="{ open: @js($active || $activeChildItems) }"
    @endif
    {{
        $attributes->class([
            'fi-sidebar-item',
            // @deprecated `fi-sidebar-item-active` has been replaced by `fi-active`.
            'fi-active fi-sidebar-item-active' => $active,
            'flex flex-col gap-y-1' => $active || $activeChildItems || $hasChildItems,
        ])
    }}
>
    @if ($hasChildItems)
        {{-- ===== PKO : item parent = toggle accordéon animé ===== --}}
        <button
            type="button"
            x-on:click="open = ! open"
            @class([
                'fi-sidebar-item-button relative flex w-full items-center justify-center gap-x-3 rounded-lg px-2 py-2 text-left outline-none transition duration-75',
                'hover:bg-gray-100 focus-visible:bg-gray-100 dark:hover:bg-white/5 dark:focus-visible:bg-white/5',
                'bg-gray-100 dark:bg-white/5' => $active || $activeChildItems,
            ])
        >
            @if (filled($icon))
                <x-filament::icon
                    :icon="($active && $activeIcon) ? $activeIcon : $icon"
                    @class([
                        'fi-sidebar-item-icon h-6 w-6',
                        'text-gray-400 dark:text-gray-500' => ! ($active || $activeChildItems),
                        'text-primary-600 dark:text-primary-400' => $active || $activeChildItems,
                    ])
                />
            @endif

            <span
                @if ($sidebarCollapsible)
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="lg:transition lg:delay-100"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                @endif
                @class([
                    'fi-sidebar-item-label flex-1 truncate text-sm font-medium',
                    'text-gray-700 dark:text-gray-200' => ! ($active || $activeChildItems),
                    'text-primary-600 dark:text-primary-400' => $active || $activeChildItems,
                ])
            >
                {{ $slot }}
            </span>

            @if (filled($badge))
                <span
                    @if ($sidebarCollapsible)
                        x-show="$store.sidebar.isOpen"
                    @endif
                >
                    <x-filament::badge :color="$badgeColor" :tooltip="$badgeTooltip">
                        {{ $badge }}
                    </x-filament::badge>
                </span>
            @endif

            {{-- PKO : flèche indiquant que le parent est dépliable (pivote à l'ouverture).
                 Rotation en style inline pour ne pas dépendre du build Tailwind. --}}
            <span
                @if ($sidebarCollapsible)
                    x-show="$store.sidebar.isOpen"
                @endif
                x-bind:style="open ? 'transform: rotate(180deg)' : 'transform: rotate(0deg)'"
                style="display: flex; flex-shrink: 0; align-items: center; justify-content: center; height: 1.25rem; width: 1.25rem; transition: transform .2s ease;"
                class="fi-sidebar-item-chevron text-gray-400 dark:text-gray-500"
            >
                {{-- plié = chevron vers le bas ; déplié = rotation 180° → vers le haut --}}
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5" />
            </span>
        </button>

        {{-- PKO : enfants indentés + ligne-guide verticale (styles inline = rendu garanti). --}}
        <ul
            x-show="open"
            x-collapse
            style="margin-inline-start: 1rem; padding-inline-start: 0.5rem; border-inline-start: 1px solid rgb(209 213 219 / 0.6);"
            class="fi-sidebar-sub-group-items flex flex-col gap-y-1"
        >
            @foreach ($childItems as $childItem)
                <x-filament-panels::sidebar.item
                    :active="$childItem->isActive()"
                    :active-child-items="$childItem->isChildItemsActive()"
                    :active-icon="$childItem->getActiveIcon()"
                    :badge="$childItem->getBadge()"
                    :badge-color="$childItem->getBadgeColor()"
                    :badge-tooltip="$childItem->getBadgeTooltip()"
                    :first="$loop->first"
                    grouped
                    :icon="$childItem->getIcon()"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$childItem->shouldOpenUrlInNewTab()"
                    sub-grouped
                    :url="$childItem->getUrl()"
                >
                    {{ $childItem->getLabel() }}
                </x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @else
        {{-- ===== Rendu natif Filament (items sans enfants) ===== --}}
        <a
            {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
            x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
            @if ($sidebarCollapsible)
                x-data="{ tooltip: false }"
                x-effect="
                    tooltip = $store.sidebar.isOpen
                        ? false
                        : {
                              content: @js($slot->toHtml()),
                              placement: document.dir === 'rtl' ? 'left' : 'right',
                              theme: $store.theme,
                          }
                "
                x-tooltip.html="tooltip"
            @endif
            @class([
                'fi-sidebar-item-button relative flex items-center justify-center gap-x-3 rounded-lg px-2 py-2 outline-none transition duration-75',
                'hover:bg-gray-100 focus-visible:bg-gray-100 dark:hover:bg-white/5 dark:focus-visible:bg-white/5' => filled($url),
                'bg-gray-100 dark:bg-white/5' => $active,
            ])
        >
            {{-- PKO : icône affichée aussi pour les items sub-grouped (pictos du sous-menu). --}}
            @if (filled($icon))
                <x-filament::icon
                    :icon="($active && $activeIcon) ? $activeIcon : $icon"
                    @class([
                        'fi-sidebar-item-icon h-6 w-6',
                        'text-gray-400 dark:text-gray-500' => ! $active,
                        'text-primary-600 dark:text-primary-400' => $active,
                    ])
                />
            @endif

            {{-- PKO : point de connexion seulement si l'item n'a PAS d'icône. --}}
            @if (blank($icon) && ($grouped || $subGrouped))
                <div
                    @if (filled($icon) && $subGrouped && $sidebarCollapsible)
                        x-show="$store.sidebar.isOpen"
                    @endif
                    class="fi-sidebar-item-grouped-border relative flex h-6 w-6 items-center justify-center"
                >
                    @if (! $first)
                        <div
                            class="absolute -top-1/2 bottom-1/2 w-px bg-gray-300 dark:bg-gray-600"
                        ></div>
                    @endif

                    @if (! $last)
                        <div
                            class="absolute -bottom-1/2 top-1/2 w-px bg-gray-300 dark:bg-gray-600"
                        ></div>
                    @endif

                    <div
                        @class([
                            'relative h-1.5 w-1.5 rounded-full',
                            'bg-gray-400 dark:bg-gray-500' => ! $active,
                            'bg-primary-600 dark:bg-primary-400' => $active,
                        ])
                    ></div>
                </div>
            @endif

            <span
                @if ($sidebarCollapsible)
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="lg:transition lg:delay-100"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                @endif
                @class([
                    'fi-sidebar-item-label flex-1 truncate text-sm font-medium',
                    'text-gray-700 dark:text-gray-200' => ! $active,
                    'text-primary-600 dark:text-primary-400' => $active,
                ])
            >
                {{ $slot }}
            </span>

            @if (filled($badge))
                <span
                    @if ($sidebarCollapsible)
                        x-show="$store.sidebar.isOpen"
                        x-transition:enter="lg:transition lg:delay-100"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                    @endif
                >
                    <x-filament::badge
                        :color="$badgeColor"
                        :tooltip="$badgeTooltip"
                    >
                        {{ $badge }}
                    </x-filament::badge>
                </span>
            @endif
        </a>
    @endif
</li>
