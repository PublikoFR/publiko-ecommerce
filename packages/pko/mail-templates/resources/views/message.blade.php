{{--
    Rendu générique d'un e-mail transactionnel à partir de blocs typés.
    Aucun texte en dur ici : tout vient de la base ou du fichier de contenus.
--}}
<x-storefront-cms::mail.layout :title="$subjectLine" :preheader="$preheader ?? null">
    @foreach ($blocks as $block)
        @switch($block['type'] ?? 'paragraph')
            @case('heading')
                <h1 style="margin:0 0 16px;font-size:20px;line-height:1.35;color:#00453e;">{{ $block['text'] ?? '' }}</h1>
                @break

            @case('button')
                @if (! empty($block['url']))
                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px auto 22px;">
                        <tr>
                            <td align="center" style="border-radius:8px;background:{{ ($block['variant'] ?? 'primary') === 'accent' ? '#aac932' : '#00453e' }};">
                                <a href="{{ $block['url'] }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:bold;color:{{ ($block['variant'] ?? 'primary') === 'accent' ? '#16201d' : '#ffffff' }};text-decoration:none;border-radius:8px;">{{ $block['label'] ?? '' }}</a>
                            </td>
                        </tr>
                    </table>
                @endif
                @break

            @case('divider')
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;">
                    <tr><td style="border-top:1px solid #eef1f0;font-size:0;line-height:0;">&nbsp;</td></tr>
                </table>
                @break

            @case('signature')
                <p style="margin:22px 0 0;font-size:15px;line-height:1.6;color:#16201d;">{!! nl2br(e($block['text'] ?? '')) !!}</p>
                @break

            @default
                <p style="margin:0 0 14px;">{!! nl2br(e($block['text'] ?? '')) !!}</p>
        @endswitch
    @endforeach
</x-storefront-cms::mail.layout>
