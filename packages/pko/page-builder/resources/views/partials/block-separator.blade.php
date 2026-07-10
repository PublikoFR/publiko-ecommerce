@php
    $variant = $block['variant'] ?? 'line';
@endphp
@if ($variant === 'space')
    <div class="pko-pb-block pko-pb-block--separator" style="height:2.5rem" aria-hidden="true"></div>
@else
    <hr class="pko-pb-block pko-pb-block--separator my-6 border-0 border-t border-neutral-200" />
@endif
