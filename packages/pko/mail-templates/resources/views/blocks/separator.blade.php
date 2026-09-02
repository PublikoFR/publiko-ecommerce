@php
    $variant = $block['variant'] ?? 'line';
    $heights = ['space-sm' => 12, 'space' => 20, 'space-md' => 20, 'space-lg' => 32, 'space-xl' => 48];
@endphp
@if ($variant === 'line')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;">
        <tr><td style="border-top:1px solid #eef1f0;font-size:0;line-height:0;">&nbsp;</td></tr>
    </table>
@else
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="height:{{ $heights[$variant] ?? 20 }}px;font-size:0;line-height:0;">&nbsp;</td></tr>
    </table>
@endif
