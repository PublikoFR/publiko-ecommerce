@php
    /** @var array{items: array<\Filament\Navigation\NavigationItem>, heading: ?string}|null $current */
    $current = \Pko\AdminNav\Filament\Support\SideNavRegistry::current();
@endphp

@if ($current)
    <aside
        x-data
        class="pko-side-nav fixed right-0 top-16 bottom-0 z-20 hidden w-64 overflow-y-auto border-l border-gray-200 bg-white px-4 py-6 dark:border-white/10 dark:bg-gray-950 lg:block"
    >
        @if ($current['heading'])
            <h3 class="mb-3 px-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ $current['heading'] }}
            </h3>
        @endif

        <ul class="flex flex-col gap-y-1">
            @foreach ($current['items'] as $item)
                @php
                    $active = $item->isActive();
                @endphp
                <li>
                    <a
                        href="{{ $item->getUrl() }}"
                        @class([
                            'flex items-center gap-x-3 rounded-lg px-2 py-2 text-sm font-medium transition',
                            'bg-gray-100 text-primary-600 dark:bg-white/5 dark:text-primary-400' => $active,
                            'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5' => ! $active,
                        ])
                    >
                        @if ($icon = $item->getIcon())
                            <x-filament::icon
                                :icon="$icon"
                                @class([
                                    'h-5 w-5',
                                    'text-primary-600 dark:text-primary-400' => $active,
                                    'text-gray-400 dark:text-gray-500' => ! $active,
                                ])
                            />
                        @endif
                        <span class="flex-1 truncate">{{ $item->getLabel() }}</span>
                        @if ($badge = $item->getBadge())
                            <x-filament::badge :color="$item->getBadgeColor()">{{ $badge }}</x-filament::badge>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </aside>

    <style>
        @media (min-width: 1024px) {
            body:has(.pko-side-nav) .fi-main {
                padding-right: 16rem;
            }
        }
    </style>
@endif
