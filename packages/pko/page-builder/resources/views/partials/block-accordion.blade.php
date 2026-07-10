@php
    $items = is_array($block['items'] ?? null) ? $block['items'] : [];
@endphp
@if (! empty($items))
    <div class="pko-pb-block pko-pb-block--accordion my-3 space-y-2">
        @foreach ($items as $item)
            @php($q = trim((string) ($item['q'] ?? '')))
            @if ($q !== '')
                <details class="group overflow-hidden rounded-lg border border-neutral-200">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 font-semibold list-none transition-colors group-open:border-b group-open:border-neutral-200"
                             style="background-color:#eef5f3;color:#00453e">
                        <span>{{ $q }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                             class="w-5 h-5 flex-none transition-transform group-open:rotate-180" style="color:#aac932"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="bg-white px-4 py-3 text-neutral-700 leading-relaxed">{{ $item['a'] ?? '' }}</div>
                </details>
            @endif
        @endforeach
    </div>
@endif
