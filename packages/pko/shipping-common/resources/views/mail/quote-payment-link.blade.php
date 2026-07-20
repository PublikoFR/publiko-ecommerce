<x-storefront-cms::mail.layout
    title="Lien de paiement — commande sur devis"
    preheader="Le montant des frais de port de votre commande #{{ $order->reference }} est disponible.">

    <h1 style="margin:0 0 16px;font-size:20px;color:#00453e;">Votre commande #{{ $order->reference }}</h1>

    <p style="margin:0 0 14px;">Suite à votre demande de devis, nous avons établi le montant des frais de port pour votre commande :</p>

    <div style="font-size:26px;font-weight:700;color:#00453e;margin:16px 0;">{{ number_format($transportCents / 100, 2, ',', ' ') }} € HT de frais de port</div>

    <p style="margin:0 0 14px;">Cliquez sur le bouton ci-dessous pour procéder au paiement :</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 16px;">
        <tr>
            <td align="center" style="border-radius:8px;background:#00453e;">
                <a href="{{ $paymentUrl }}" style="display:inline-block;padding:14px 30px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">Payer ma commande</a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px;font-size:12px;color:#98a29e;word-break:break-all;">
        Lien direct : <a href="{{ $paymentUrl }}" style="color:#76817d;">{{ $paymentUrl }}</a>
    </p>

    <p style="margin:0;font-size:13px;color:#76817d;">Ce lien est sécurisé et valide 7 jours.</p>
</x-storefront-cms::mail.layout>
