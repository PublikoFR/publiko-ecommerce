@php
    $text = trim((string) ($block['text'] ?? ''));
    $cite = trim((string) ($block['cite'] ?? ''));
@endphp
@if ($text !== '')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;">
        <tr>
            <td style="background:#f6f8f7;border-left:4px solid #aac932;padding:14px 18px;">
                <p style="margin:0;font-size:16px;font-style:italic;line-height:1.6;color:#16201d;">{{ $text }}</p>
                @if ($cite !== '')
                    <p style="margin:8px 0 0;font-size:13px;color:#586460;">— {{ $cite }}</p>
                @endif
            </td>
        </tr>
    </table>
@endif
