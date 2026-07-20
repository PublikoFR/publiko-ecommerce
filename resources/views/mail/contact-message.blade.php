<x-storefront-cms::mail.layout
    title="Nouveau message de contact"
    preheader="{{ $senderName }} vous a envoyé un message via le formulaire de contact.">

    <h1 style="margin:0 0 16px;font-size:20px;color:#00453e;">Nouveau message de contact</h1>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
        <tr>
            <td style="padding:6px 0;color:#586460;width:120px;">Nom</td>
            <td style="padding:6px 0;font-weight:bold;">{{ $senderName }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#586460;">E-mail</td>
            <td style="padding:6px 0;"><a href="mailto:{{ $senderEmail }}" style="color:#00453e;">{{ $senderEmail }}</a></td>
        </tr>
        @if ($senderPhone !== '')
            <tr>
                <td style="padding:6px 0;color:#586460;">Téléphone</td>
                <td style="padding:6px 0;"><a href="tel:{{ $senderPhone }}" style="color:#00453e;">{{ $senderPhone }}</a></td>
            </tr>
        @endif
        <tr>
            <td style="padding:6px 0;color:#586460;">Sujet</td>
            <td style="padding:6px 0;font-weight:bold;">{{ $subjectLine }}</td>
        </tr>
    </table>

    <div style="margin-top:18px;padding:16px;background:#f6f8f7;border:1px solid #e0e4e2;border-radius:8px;white-space:pre-wrap;font-size:14px;line-height:1.6;">{{ $body }}</div>

    <p style="margin:20px 0 0;font-size:12px;color:#76817d;">
        Répondez directement à cet e-mail pour contacter {{ $senderName }}.
    </p>
</x-storefront-cms::mail.layout>
