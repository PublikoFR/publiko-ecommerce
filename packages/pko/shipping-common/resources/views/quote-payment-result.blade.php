<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement — commande #{{ $order->reference }} | {{ brand_name() }}</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 8px; max-width: 560px; margin: 0 auto; padding: 32px;
                box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
        .brand { font-size: 14px; font-weight: 600; color: #f59e0b; text-transform: uppercase; letter-spacing: .04em; }
        .icon { font-size: 48px; margin: 16px 0; }
        h1 { font-size: 22px; margin: 8px 0 12px; }
        p { color: #444; font-size: 15px; }
        .ok { color: #16a34a; }
        .pending { color: #f59e0b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">{{ brand_name() }}</div>

        @if ($paid)
            <div class="icon ok">✓</div>
            <h1>Paiement confirmé</h1>
            <p>Merci, votre paiement pour la commande <strong>#{{ $order->reference }}</strong> a bien été enregistré.</p>
            <p>Votre commande est désormais en cours de préparation. Vous recevrez un e-mail dès l'expédition.</p>
        @else
            <div class="icon pending">⏳</div>
            <h1>Paiement en cours de traitement</h1>
            <p>Le paiement de la commande <strong>#{{ $order->reference }}</strong> n'est pas encore confirmé.</p>
            <p>S'il a été débité, le statut sera mis à jour sous peu. En cas de doute, contactez-nous.</p>
        @endif
    </div>
</body>
</html>
