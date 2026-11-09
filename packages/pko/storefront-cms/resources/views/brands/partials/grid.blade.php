<ul class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
    @foreach ($items as $item)
        <li>
            <a
                href="{{ $item['href'] }}"
                class="group flex flex-col items-center gap-3 h-full p-4 bg-white border border-neutral-200 rounded-lg hover:border-primary-500 hover:shadow-md transition"
                data-brand-id="{{ $item['brand']->id }}"
            >
                <span class="w-full aspect-[3/2] flex items-center justify-center">
                    @if ($item['logo'])
                        <img src="{{ $item['logo'] }}" alt="{{ $item['brand']->name }}" class="max-w-full max-h-full object-contain" loading="lazy">
                    @else
                        <span class="text-lg font-display font-bold text-neutral-700 text-center break-words group-hover:text-primary-700 transition">{{ $item['brand']->name }}</span>
                    @endif
                </span>
                @if ($item['logo'])
                    <span class="text-sm font-semibold text-neutral-700 text-center group-hover:text-primary-700 transition">{{ $item['brand']->name }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
