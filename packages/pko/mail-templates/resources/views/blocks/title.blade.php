@php
    $level = in_array($block['level'] ?? null, ['h2', 'h3'], true) ? $block['level'] : 'h2';
    $fontSize = $level === 'h3' ? '17px' : '20px';
@endphp
@if (trim((string) ($block['text'] ?? '')) !== '')
    <{{ $level }} style="margin:0 0 14px;font-size:{{ $fontSize }};line-height:1.35;color:#00453e;">{{ $block['text'] }}</{{ $level }}>
@endif
