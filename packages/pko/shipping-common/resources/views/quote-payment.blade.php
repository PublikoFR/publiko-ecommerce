<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement — commande #{{ $order->reference }} | {{ brand_name() }}</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 8px; max-width: 560px; margin: 0 auto; padding: 32px;
                box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .brand { font-size: 14px; font-weight: 600; color: #f59e0b; text-transform: uppercase; letter-spacing: .04em; }
        h1 { font-size: 20px; margin: 8px 0 16px; }
        .lines { font-size: 14px; color: #444; border-top: 1px solid #eee; padding-top: 16px; }
        .lines div { display: flex; justify-content: space-between; padding: 4px 0; }
        .total { font-size: 22px; font-weight: 700; color: #1a1a1a; border-top: 2px solid #eee;
                 padding-top: 12px; margin-top: 8px; }
        #payment-element { margin: 24px 0; }
        .btn { width: 100%; background: #f59e0b; color: #fff; border: none; font-size: 16px;
               font-weight: 600; padding: 14px; border-radius: 6px; cursor: pointer; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        #message { color: #b91c1c; font-size: 14px; margin-top: 12px; min-height: 18px; }
        .footer { font-size: 12px; color: #888; margin-top: 24px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">{{ brand_name() }}</div>
        <h1>Paiement de votre commande #{{ $order->reference }}</h1>

        <div class="lines">
            <div><span>Sous-total commande</span><span>{{ number_format($order->total->value / 100, 2, ',', ' ') }} € HT</span></div>
            <div><span>Frais de port</span><span>{{ number_format($transportCents / 100, 2, ',', ' ') }} € HT</span></div>
            <div class="total"><span>Total à payer</span><span>{{ number_format($amount / 100, 2, ',', ' ') }} € HT</span></div>
        </div>

        <form id="payment-form">
            <div id="payment-element"></div>
            <button id="submit" class="btn" type="submit">Payer {{ number_format($amount / 100, 2, ',', ' ') }} €</button>
            <div id="message"></div>
        </form>

        <div class="footer">Paiement sécurisé. Vos données bancaires sont traitées par Stripe.</div>
    </div>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        const stripe = Stripe(@json($publishableKey));
        const clientSecret = @json($clientSecret);
        const returnUrl = @json($returnUrl);

        const elements = stripe.elements({ clientSecret });
        elements.create('payment').mount('#payment-element');

        const form = document.getElementById('payment-form');
        const submitBtn = document.getElementById('submit');
        const message = document.getElementById('message');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            message.textContent = '';

            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: { return_url: returnUrl },
            });

            if (error) {
                message.textContent = error.message || 'Le paiement a échoué, veuillez réessayer.';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
