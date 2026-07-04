<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement — commande #{{ $order->reference }} | {{ brand_name() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Weklo Design System (page autonome — tokens en dur) */
        body { font-family: 'Hanken Grotesk', system-ui, sans-serif; color: #16201d; background: #f6f8f7; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 20px; max-width: 560px; margin: 0 auto; padding: 40px 32px;
                border: 1px solid #e0e4e2; box-shadow: 0 4px 12px rgba(0,33,30,.08); text-align: center; }
        .brand { font-size: 12px; font-weight: 600; color: #6a841d; text-transform: uppercase; letter-spacing: .08em; }
        .badge { width: 64px; height: 64px; border-radius: 9999px; margin: 20px auto 4px;
                 display: flex; align-items: center; justify-content: center; }
        .badge.ok { background: #d9f0e0; color: #1f6e3c; }
        .badge.pending { background: #fbeccb; color: #a36f08; }
        h1 { font-size: 24px; font-weight: 700; margin: 12px 0; color: #16201d; }
        p { color: #586460; font-size: 15px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">{{ brand_name() }}</div>

        @if ($paid)
            <div class="badge ok">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
            </div>
            <h1>Paiement confirmé</h1>
            <p>Merci, votre paiement pour la commande <strong>#{{ $order->reference }}</strong> a bien été enregistré.</p>
            <p>Votre commande est désormais en cours de préparation. Vous recevrez un e-mail dès l'expédition.</p>
        @else
            <div class="badge pending">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </div>
            <h1>Paiement en cours de traitement</h1>
            <p>Le paiement de la commande <strong>#{{ $order->reference }}</strong> n'est pas encore confirmé.</p>
            <p>S'il a été débité, le statut sera mis à jour sous peu. En cas de doute, contactez-nous.</p>
        @endif
    </div>
</body>
</html>
