<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Génère l'URL signée de vérification d'e-mail (route `verification.verify`).
 *
 * Point unique partagé par le mail de bienvenue (CustomerRegisteredMail) et le
 * renvoi manuel (EmailVerificationMail), pour garantir un lien cohérent :
 * signature temporaire + hash de l'e-mail, validés par le middleware `signed`.
 */
class EmailVerification
{
    public static function signedUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addDays(7),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }
}
