@php
    $items = is_array($block['items'] ?? null) ? $block['items'] : [];
@endphp
@if (! empty($items))
    <div class="pko-pb-block pko-pb-block--accordion my-3 divide-y divide-neutral-200 rounded-lg border border-neutral-200">
        @foreach ($items as $item)
            @php($q = trim((string) ($item['q'] ?? '')))
            @if ($q !== '')
                <details class="group">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 font-semibold text-neutral-900 list-none">
                        <span>{{ $q }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 flex-none text-neutral-400 transition-transform group-open:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="px-4 pb-4 pt-0 text-neutral-700 leading-relaxed">{{ $item['a'] ?? '' }}</div>
                </details>
            @endif
        @endforeach
    </div>
@endif
