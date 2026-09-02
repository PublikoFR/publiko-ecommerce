@php
    $variant = $block['variant'] ?? 'primary';
    $colors = match ($variant) {
        'accent' => ['bg' => '#aac932', 'text' => '#16201d'],
        'secondary' => ['bg' => '#ffffff', 'text' => '#00453e'],
        default => ['bg' => '#00453e', 'text' => '#ffffff'],
    };
    $border = $variant === 'secondary' ? 'border:1px solid #c3ccc8;' : '';
@endphp
@if (! empty($block['url']) && ! empty($block['label']))
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px auto 22px;">
        <tr>
            <td align="center" style="border-radius:8px;background:{{ $colors['bg'] }};{{ $border }}">
                <a href="{{ $block['url'] }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:bold;color:{{ $colors['text'] }};text-decoration:none;border-radius:8px;">{{ $block['label'] }}</a>
            </td>
        </tr>
    </table>
@endif
