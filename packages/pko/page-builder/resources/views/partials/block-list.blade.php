@php
    $items = is_array($block['items'] ?? null) ? $block['items'] : [];
    $isCheck = ($block['style'] ?? 'bullet') === 'check';
@endphp
@if (! empty($items))
    <ul class="pko-pb-block pko-pb-block--list my-2 space-y-1.5 {{ $isCheck ? '' : 'list-disc pl-5' }}">
        @foreach ($items as $item)
            <li class="text-neutral-800 leading-relaxed {{ $isCheck ? 'flex items-start gap-2' : '' }}">
                @if ($isCheck)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 flex-none mt-0.5 text-accent-600"><path d="M20 6 9 17l-5-5"/></svg>
                    <span>{{ $item }}</span>
                @else
                    {{ $item }}
                @endif
            </li>
        @endforeach
    </ul>
@endif
