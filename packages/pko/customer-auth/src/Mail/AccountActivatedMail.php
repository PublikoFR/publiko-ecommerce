<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Mail;

use Lunar\Models\Customer;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * E-mail 02 « Compte validé ».
 *
 * Envoyé au passage de `pko_status` de `pending` à `active`, c'est-à-dire à la
 * validation manuelle du compte par l'équipe en back-office (action « Valider le
 * compte » ou Select « Statut compte ») — cf. CustomerActivationObserver.
 */
class AccountActivatedMail extends TemplatedMail
{
    public function __construct(public readonly Customer $customer)
    {
        parent::__construct('account.activated', [
            'first_name' => (string) ($customer->first_name ?? ''),
            'company_name' => (string) ($customer->company_name ?? ''),
            'login_url' => route('login'),
            'account_url' => url('/compte'),
        ]);
    }
}
