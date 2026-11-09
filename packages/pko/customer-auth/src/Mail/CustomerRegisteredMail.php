<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Mail;

use App\Models\User;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Support\EmailVerification;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * E-mail 01 « Bienvenue / inscription du compte ».
 *
 * Porte le lien de vérification (route `verification.verify`). Confirmer l'e-mail
 * n'active PAS le compte : il reste en attente de validation par l'équipe, qui
 * est alors prévenue (CustomerRegisteredAdminMail). L'e-mail 02 part à la
 * validation manuelle.
 */
class CustomerRegisteredMail extends TemplatedMail
{
    public function __construct(
        public readonly Customer $customer,
        public readonly User $user,
    ) {
        parent::__construct('account.welcome', [
            'first_name' => (string) ($customer->first_name ?? ''),
            'company_name' => (string) ($customer->company_name ?? ''),
            'verify_url' => EmailVerification::signedUrl($user),
        ]);
    }
}
