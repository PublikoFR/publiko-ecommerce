<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Pko\CustomerAuth\Support\EmailVerification;

/**
 * Renvoi manuel du lien de vérification d'e-mail (déclenché depuis le bandeau
 * de rappel côté storefront). Le mail de bienvenue contient déjà ce lien ;
 * ce mailable sert au cas où l'utilisateur l'a perdu.
 */
class EmailVerificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Vérifiez votre adresse e-mail — '.brand_name())
            ->view('customer-auth::mail.verify-email', [
                'verifyUrl' => EmailVerification::signedUrl($this->user),
            ]);
    }
}
