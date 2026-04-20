@if ($hasSections())
    <div class="pko-page-builder">
        @foreach ($tree['sections'] as $section)
            @include('page-builder::partials.section', ['section' => $section])
        @endforeach
    </div>
@elseif ($fallback !== null && trim($fallback) !== '')
    {{-- Fallback HTML brut (legacy `body`). --}}
    <div class="pko-page-builder pko-page-builder--fallback prose max-w-none">
        {!! $fallback !!}
    </div>
@endif
