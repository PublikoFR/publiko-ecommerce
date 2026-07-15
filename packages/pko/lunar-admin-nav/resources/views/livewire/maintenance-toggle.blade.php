<button
    wire:click="toggle"
    title="{{ $active ? 'Site en maintenance — cliquer pour réouvrir la boutique' : 'Mettre le site en maintenance' }}"
    class="hidden md:inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors {{ $active ? 'bg-warning-100 text-warning-700 border border-warning-300' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' }}"
>
    @if ($active)
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
            <path d="M12 8v4M12 16h.01"/>
        </svg>
        Maintenance active
    @else
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <path d="M12 9v4M12 17h.01"/>
        </svg>
        Maintenance
    @endif
</button>
