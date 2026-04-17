@props(['items' => []])

<nav aria-label="Fil d'Ariane" class="text-sm">
    <ol class="flex flex-wrap items-center gap-1.5 text-neutral-500">
        <li>
            <a href="/" class="hover:text-primary-600 transition">Accueil</a>
        </li>
        @foreach ($items as $item)
            <li aria-hidden="true" class="text-neutral-300"><x-ui.icon name="chevron-right" class="w-3.5 h-3.5" /></li>
            <li>
                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" class="hover:text-primary-600 transition">{{ $item['label'] }}</a>
                @else
                    <span class="text-neutral-900 font-medium">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
