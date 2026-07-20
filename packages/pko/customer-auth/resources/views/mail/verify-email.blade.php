<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifiez votre adresse e-mail</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 8px; max-width: 560px; margin: 0 auto; padding: 32px; }
        h1 { font-size: 20px; margin-bottom: 8px; color: #00453e; }
        p { line-height: 1.6; margin: 12px 0; }
        .btn { display: inline-block; background: #00453e; color: #fff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin: 8px 0; }
        .muted { font-size: 13px; color: #666; word-break: break-all; }
        .footer { font-size: 12px; color: #666; margin-top: 32px; border-top: 1px solid #eee; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Vérifiez votre adresse e-mail</h1>

        <p>Bonjour,</p>

        <p>Confirmez votre adresse e-mail pour sécuriser votre compte {{ brand_name() }} :</p>

        <p style="text-align: center;">
            <a href="{{ $verifyUrl }}" class="btn">Vérifier mon adresse e-mail</a>
        </p>

        <p class="muted">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>{{ $verifyUrl }}</p>

        <p>Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.</p>

        <div class="footer">
            {{ brand_name() }} — cet e-mail a été envoyé automatiquement, merci de ne pas y répondre.
        </div>
    </div>
</body>
</html>
