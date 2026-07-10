@php
    $p = $section['padding'];
    $m = $section['margin'];
    $styleParts = [
        'padding:' . $p['t'] . 'px ' . $p['r'] . 'px ' . $p['b'] . 'px ' . $p['l'] . 'px',
        'margin-top:' . $m['t'] . 'px',
        'margin-bottom:' . $m['b'] . 'px',
    ];
    if (! empty($section['background_color'])) {
        $styleParts[] = 'background-color:' . $section['background_color'];
    }
    if (! empty($section['text_color'])) {
        $styleParts[] = 'color:' . $section['text_color'];
    }
    $style = implode(';', $styleParts);

    $gridCols = match ($section['layout']) {
        '2col' => 'grid-cols-1 md:grid-cols-2',
        '3col' => 'grid-cols-1 md:grid-cols-3',
        '4col' => 'grid-cols-2 md:grid-cols-4',
        '5col' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
        '6col' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-6',
        default => 'grid-cols-1',
    };
@endphp

<section class="pko-pb-section" style="{{ $style }}">
    <div class="grid {{ $gridCols }} gap-6">
        @foreach ($section['columns'] as $column)
            <div class="pko-pb-column">
                @foreach ($column['blocks'] as $block)
                    @switch($block['type'])
                        @case('text')
                            @include('page-builder::partials.block-text', ['block' => $block])
                            @break
                        @case('image')
                            @include('page-builder::partials.block-image', ['block' => $block])
                            @break
                        @case('code')
                            @include('page-builder::partials.block-code', ['block' => $block])
                            @break
                        @case('quote')
                            @include('page-builder::partials.block-quote', ['block' => $block])
                            @break
                        @case('button')
                            @include('page-builder::partials.block-button', ['block' => $block])
                            @break
                        @case('separator')
                            @include('page-builder::partials.block-separator', ['block' => $block])
                            @break
                        @case('callout')
                            @include('page-builder::partials.block-callout', ['block' => $block])
                            @break
                    @endswitch
                @endforeach
            </div>
        @endforeach
    </div>
</section>
