@php
    $variant = $block['variant'] ?? 'line';
    $heights = ['space-sm' => '1.5rem', 'space' => '2.5rem', 'space-md' => '2.5rem', 'space-lg' => '4rem', 'space-xl' => '6rem'];
@endphp
@if ($variant === 'line')
    <hr class="pko-pb-block pko-pb-block--separator my-6 border-0 border-t border-neutral-200" />
@else
    <div class="pko-pb-block pko-pb-block--separator" style="height:{{ $heights[$variant] ?? '2.5rem' }}" aria-hidden="true"></div>
@endif
