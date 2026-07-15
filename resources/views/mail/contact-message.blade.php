<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau message de contact</title>
</head>
<body style="margin:0;padding:24px;background:#f6f8f7;font-family:Arial,Helvetica,sans-serif;color:#16201d;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e0e4e2;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="background:#00453e;padding:20px 28px;">
                <h1 style="margin:0;font-size:18px;color:#ffffff;">Nouveau message de contact</h1>
                <p style="margin:4px 0 0;font-size:13px;color:#aac932;">{{ brand_name() }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
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
            </td>
        </tr>
    </table>
</body>
</html>
