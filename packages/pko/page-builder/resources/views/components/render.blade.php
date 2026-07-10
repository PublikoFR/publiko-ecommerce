@if ($withHeading && trim($heading()) !== '')
    <h1 class="pko-pb-heading text-3xl md:text-4xl font-display font-bold leading-tight text-neutral-900">{{ $heading() }}</h1>
@endif

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

{{-- JSON-LD FAQPage agrégé (SEO, invisible) — émis dès qu'un bloc accordéon a une paire Q/R. --}}
@if ($faq = $faqJsonLd())
    <script type="application/ld+json">{!! $faq !!}</script>
@endif
