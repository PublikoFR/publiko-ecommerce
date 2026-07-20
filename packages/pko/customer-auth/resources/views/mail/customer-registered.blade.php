<x-storefront-cms::mail.layout
    title="Confirmation de votre inscription"
    preheader="Votre compte professionnel a bien été créé.">

    <h1 style="margin:0 0 16px;font-size:20px;color:#00453e;">Bienvenue sur {{ brand_name() }}</h1>

    <p style="margin:0 0 14px;">Bonjour{{ $customer->company_name ? ' — '.$customer->company_name : '' }},</p>

    <p style="margin:0 0 14px;">Votre compte professionnel a bien été créé. Vous pouvez dès à présent accéder à votre espace et consulter nos produits et tarifs réservés aux professionnels.</p>

    @if ($customer->pko_status === 'pending')
        <p style="margin:0 0 14px;">La vérification de votre SIRET est en cours. Vous recevrez une confirmation par e-mail dès que votre compte sera pleinement activé.</p>
    @endif

    <p style="margin:0 0 14px;"><strong>Confirmez votre adresse e-mail</strong> pour sécuriser votre compte :</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px auto 20px;">
        <tr>
            <td align="center" style="border-radius:8px;background:#00453e;">
                <a href="{{ $verifyUrl }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">Vérifier mon adresse e-mail</a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px;font-size:13px;color:#76817d;word-break:break-all;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>{{ $verifyUrl }}</p>

    <p style="margin:0;">Si vous n'êtes pas à l'origine de cette inscription, ignorez cet e-mail.</p>
</x-storefront-cms::mail.layout>
