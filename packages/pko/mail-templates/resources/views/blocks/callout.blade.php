@php
    $text = trim((string) ($block['text'] ?? ''));
    $variant = $block['variant'] ?? 'info';
    $colors = match ($variant) {
        'warning' => ['bg' => '#fbeccb', 'border' => '#e8a317', 'text' => '#a36f08'],
        'danger' => ['bg' => '#f9dcdc', 'border' => '#d64545', 'text' => '#9c2a2a'],
        default => ['bg' => '#d6e9f3', 'border' => '#2f80b8', 'text' => '#1d5781'],
    };
@endphp
@if ($text !== '')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;">
        <tr>
            <td style="background:{{ $colors['bg'] }};border-left:4px solid {{ $colors['border'] }};padding:12px 16px;font-size:14px;line-height:1.6;color:{{ $colors['text'] }};">
                {{ $text }}
            </td>
        </tr>
    </table>
@endif
