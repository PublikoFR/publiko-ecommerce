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
        .card { background: #fff; border-radius: 20px; max-width: 560px; margin: 0 auto; padding: 32px;
                border: 1px solid #e0e4e2; box-shadow: 0 4px 12px rgba(0,33,30,.08); }
        .brand { font-size: 12px; font-weight: 600; color: #6a841d; text-transform: uppercase; letter-spacing: .08em; }
        h1 { font-size: 22px; font-weight: 700; margin: 8px 0 16px; color: #16201d; }
        .lines { font-size: 14px; color: #586460; border-top: 1px solid #eef1f0; padding-top: 16px; }
        .lines div { display: flex; justify-content: space-between; padding: 4px 0; }
        .total { font-size: 24px; font-weight: 700; color: #00453e; border-top: 2px solid #eef1f0;
                 padding-top: 12px; margin-top: 8px; }
        #payment-element { margin: 24px 0; }
        .btn { width: 100%; background: #00453e; color: #fff; border: none; font-size: 16px;
               font-weight: 600; padding: 14px; border-radius: 10px; cursor: pointer; transition: background .2s; }
        .btn:hover { background: #003a34; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        #message { color: #9c2a2a; font-size: 14px; margin-top: 12px; min-height: 18px; }
        .footer { font-size: 12px; color: #76817d; margin-top: 24px; text-align: center; }
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
