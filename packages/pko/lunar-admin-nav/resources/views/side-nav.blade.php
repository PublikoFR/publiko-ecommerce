@php
    /** @var array{items: array<\Filament\Navigation\NavigationItem>, heading: ?string}|null $current */
    $current = \Pko\AdminNav\Filament\Support\SideNavRegistry::current();
@endphp

@if ($current)
    <style>
        .pko-side-nav {
            position: fixed;
            top: 4rem;
            right: 0;
            bottom: 0;
            width: 16rem;
            overflow-y: auto;
            border-left: 1px solid rgb(229 231 235);
            background-color: rgb(255 255 255);
            padding: 1.5rem 1rem;
            z-index: 20;
        }
        .dark .pko-side-nav {
            border-color: rgb(255 255 255 / 0.1);
            background-color: rgb(9 9 11);
        }
        @media (max-width: 1023px) {
            .pko-side-nav { display: none; }
        }
        @media (min-width: 1024px) {
            body:has(.pko-side-nav) .fi-main,
            body:has(.pko-side-nav) .fi-topbar {
                padding-right: 16rem;
            }
        }
        .pko-side-nav__heading {
            margin-bottom: 0.75rem;
            padding: 0 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgb(107 114 128);
        }
        .dark .pko-side-nav__heading { color: rgb(156 163 175); }
        .pko-side-nav__list {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .pko-side-nav__link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 0.5rem;
            padding: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: rgb(55 65 81);
            text-decoration: none;
            transition: background-color 0.15s;
        }
        .pko-side-nav__link:hover {
            background-color: rgb(243 244 246);
        }
        .pko-side-nav__link.is-active {
            background-color: rgb(243 244 246);
            color: rgb(37 99 235);
        }
        .dark .pko-side-nav__link { color: rgb(229 231 235); }
        .dark .pko-side-nav__link:hover,
        .dark .pko-side-nav__link.is-active {
            background-color: rgb(255 255 255 / 0.05);
        }
        .dark .pko-side-nav__link.is-active { color: rgb(96 165 250); }
        .pko-side-nav__icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
        .pko-side-nav__icon--inactive { color: rgb(156 163 175); }
        .pko-side-nav__icon--active { color: rgb(37 99 235); }
        .dark .pko-side-nav__icon--active { color: rgb(96 165 250); }
        .pko-side-nav__label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>

    <aside class="pko-side-nav">
        @if ($current['heading'])
            <h3 class="pko-side-nav__heading">{{ $current['heading'] }}</h3>
        @endif

        <ul class="pko-side-nav__list">
            @foreach ($current['items'] as $item)
                @php $active = $item->isActive(); @endphp
                <li>
                    <a
                        href="{{ $item->getUrl() }}"
                        class="pko-side-nav__link {{ $active ? 'is-active' : '' }}"
                    >
                        @if ($icon = $item->getIcon())
                            <x-filament::icon
                                :icon="$icon"
                                class="pko-side-nav__icon {{ $active ? 'pko-side-nav__icon--active' : 'pko-side-nav__icon--inactive' }}"
                            />
                        @endif
                        <span class="pko-side-nav__label">{{ $item->getLabel() }}</span>
                        @if ($badge = $item->getBadge())
                            <x-filament::badge :color="$item->getBadgeColor()">{{ $badge }}</x-filament::badge>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </aside>
@endif
