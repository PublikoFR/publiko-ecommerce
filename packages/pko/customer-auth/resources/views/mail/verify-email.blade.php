<x-storefront-cms::mail.layout
    title="Vérifiez votre adresse e-mail"
    preheader="Confirmez votre adresse e-mail pour sécuriser votre compte.">

    <h1 style="margin:0 0 16px;font-size:20px;color:#00453e;">Vérifiez votre adresse e-mail</h1>

    <p style="margin:0 0 14px;">Bonjour,</p>

    <p style="margin:0 0 14px;">Confirmez votre adresse e-mail pour sécuriser votre compte {{ brand_name() }} :</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px auto 20px;">
        <tr>
            <td align="center" style="border-radius:8px;background:#00453e;">
                <a href="{{ $verifyUrl }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">Vérifier mon adresse e-mail</a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px;font-size:13px;color:#76817d;word-break:break-all;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>{{ $verifyUrl }}</p>

    <p style="margin:0;">Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.</p>
</x-storefront-cms::mail.layout>
