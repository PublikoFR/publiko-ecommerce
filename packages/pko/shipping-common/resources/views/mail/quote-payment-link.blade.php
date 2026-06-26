<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lien de paiement — commande sur devis</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 8px; max-width: 560px; margin: 0 auto; padding: 32px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        .amount { font-size: 28px; font-weight: 700; color: #f59e0b; margin: 16px 0; }
        .btn { display: inline-block; background: #f59e0b; color: #fff; text-decoration: none;
               font-weight: 600; padding: 14px 32px; border-radius: 6px; margin-top: 24px; }
        .footer { font-size: 12px; color: #666; margin-top: 32px; }
        .url { word-break: break-all; font-size: 12px; color: #888; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Votre commande #{{ $order->reference }}</h1>
        <p>Suite à votre demande de devis, nous avons établi le montant des frais de port pour votre commande :</p>

        <div class="amount">{{ number_format($transportCents / 100, 2, ',', ' ') }} € HT de frais de port</div>

        <p>Cliquez sur le bouton ci-dessous pour procéder au paiement :</p>

        <a href="{{ $paymentUrl }}" class="btn">Payer ma commande</a>

        <div class="url">
            Lien direct : <a href="{{ $paymentUrl }}">{{ $paymentUrl }}</a>
        </div>

        <div class="footer">
            Ce lien est sécurisé et valide 7 jours. Si vous avez des questions, répondez directement à cet e-mail.
        </div>
    </div>
</body>
</html>
