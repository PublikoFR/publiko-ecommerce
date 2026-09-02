@php
    $items = is_array($block['items'] ?? null) ? $block['items'] : [];
    $isCheck = ($block['style'] ?? 'bullet') === 'check';
@endphp
@if (! empty($items))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;">
        @foreach ($items as $item)
            <tr>
                <td style="width:20px;vertical-align:top;padding:2px 0;font-size:15px;line-height:1.6;color:{{ $isCheck ? '#00453e' : '#16201d' }};">{{ $isCheck ? '✓' : '•' }}</td>
                <td style="padding:2px 0;font-size:15px;line-height:1.6;color:#16201d;">{{ $item }}</td>
            </tr>
        @endforeach
    </table>
@endif
