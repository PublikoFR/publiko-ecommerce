<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre commande a été expédiée</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Helvetica,Arial,sans-serif;color:#222;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px;border-bottom:1px solid #eee;">
                            <h1 style="margin:0;font-size:20px;color:#111;">{{ $brandName }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <h2 style="margin:0 0 12px 0;font-size:18px;">Votre commande est en route</h2>
                            <p style="margin:0 0 16px 0;line-height:1.5;">
                                Bonne nouvelle : votre commande <strong>{{ $orderReference }}</strong> vient d'être confiée à <strong>{{ $carrierLabel }}</strong>.
                            </p>
                            <p style="margin:0 0 16px 0;line-height:1.5;">
                                Vous pouvez suivre son acheminement avec le numéro de suivi :
                            </p>
                            <p style="margin:0 0 20px 0;font-family:'Courier New',monospace;font-size:15px;padding:10px 14px;background:#f7f7f9;border:1px solid #e3e3e7;border-radius:6px;">
                                {{ $shipment->tracking_number }}
                            </p>
                            <p style="margin:0 0 24px 0;">
                                <a href="{{ $trackingUrl }}"
                                   style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;">
                                    Suivre mon colis
                                </a>
                            </p>
                            <p style="margin:0 0 8px 0;font-size:13px;color:#666;line-height:1.5;">
                                Le suivi devient disponible dans les heures qui suivent la remise au transporteur. En cas de question, répondez simplement à cet email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px;border-top:1px solid #eee;font-size:12px;color:#999;text-align:center;">
                            © {{ date('Y') }} {{ $brandName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
