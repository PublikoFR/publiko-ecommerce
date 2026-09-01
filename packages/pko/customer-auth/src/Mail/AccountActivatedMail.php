<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Mail;

use Lunar\Models\Customer;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * E-mail 02 « Activation du compte ».
 *
 * Envoyé au passage de `pko_status` de `pending` à `active`, c'est-à-dire à la
 * vérification de l'adresse e-mail — seul critère d'activation dans ce projet.
 */
class AccountActivatedMail extends TemplatedMail
{
    public function __construct(public readonly Customer $customer)
    {
        parent::__construct('account.activated', [
            'first_name' => (string) ($customer->first_name ?? ''),
            'company_name' => (string) ($customer->company_name ?? ''),
            'account_url' => url('/compte'),
        ]);
    }
}
