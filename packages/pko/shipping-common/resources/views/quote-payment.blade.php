<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement commande #{{ $order->reference }}</title>
</head>
<body>
    <h1>Commande #{{ $order->reference }}</h1>
    <p>Frais de port : {{ number_format($transportCents / 100, 2, ',', ' ') }} € HT</p>
    {{-- TODO: intégrer le formulaire de paiement Stripe ici --}}
    <p><em>Page de paiement en cours de finalisation. Contactez-nous pour procéder au règlement.</em></p>
</body>
</html>
