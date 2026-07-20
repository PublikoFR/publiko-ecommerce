<x-storefront-cms::mail.layout
    title="Votre commande a été expédiée"
    preheader="Votre commande {{ $orderReference }} vient d'être confiée à {{ $carrierLabel }}.">

    <h1 style="margin:0 0 16px;font-size:20px;color:#00453e;">Votre commande est en route</h1>

    <p style="margin:0 0 14px;line-height:1.6;">
        Bonne nouvelle : votre commande <strong>{{ $orderReference }}</strong> vient d'être confiée à <strong>{{ $carrierLabel }}</strong>.
    </p>
    <p style="margin:0 0 14px;line-height:1.6;">
        Vous pouvez suivre son acheminement avec le numéro de suivi :
    </p>
    <p style="margin:0 0 20px;font-family:'Courier New',monospace;font-size:15px;padding:10px 14px;background:#f6f8f7;border:1px solid #e0e4e2;border-radius:6px;">
        {{ $shipment->tracking_number }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;">
        <tr>
            <td align="center" style="border-radius:8px;background:#00453e;">
                <a href="{{ $trackingUrl }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">Suivre mon colis</a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#76817d;line-height:1.6;">
        Le suivi devient disponible dans les heures qui suivent la remise au transporteur.
    </p>
</x-storefront-cms::mail.layout>
