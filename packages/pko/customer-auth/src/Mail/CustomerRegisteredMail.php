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
 * Porte le lien de vérification : c'est lui qui déclenche ensuite l'activation
 * du compte (cf. route `verification.verify`) et l'e-mail 02.
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
