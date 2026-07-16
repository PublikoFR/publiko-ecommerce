<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d'inscription</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 8px; max-width: 560px; margin: 0 auto; padding: 32px; }
        h1 { font-size: 20px; margin-bottom: 8px; color: #00453e; }
        p { line-height: 1.6; margin: 12px 0; }
        .footer { font-size: 12px; color: #666; margin-top: 32px; border-top: 1px solid #eee; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Bienvenue sur {{ brand_name() }}</h1>

        <p>Bonjour{{ $customer->company_name ? ' — ' . $customer->company_name : '' }},</p>

        <p>Votre compte professionnel a bien été créé. Vous pouvez dès à présent accéder à votre espace et consulter nos produits et tarifs réservés aux professionnels.</p>

        @if ($customer->pko_status === 'pending')
            <p>La vérification de votre SIRET est en cours. Vous recevrez une confirmation par e-mail dès que votre compte sera pleinement activé.</p>
        @endif

        <p>Si vous n'êtes pas à l'origine de cette inscription, ignorez cet e-mail.</p>

        <div class="footer">
            {{ brand_name() }} — cet e-mail a été envoyé automatiquement, merci de ne pas y répondre.
        </div>
    </div>
</body>
</html>
