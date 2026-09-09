<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Mail;

use App\Models\User;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Support\CustomerAdminMailData;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * Notification équipe à chaque inscription client (onboarding téléphone).
 *
 * Reply-To = e-mail du client : un « répondre » ouvre directement le fil.
 */
class CustomerRegisteredAdminMail extends TemplatedMail
{
    public function __construct(
        public readonly Customer $customer,
        public readonly User $user,
    ) {
        parent::__construct('account.registered_admin', CustomerAdminMailData::values($customer, $user));
    }

    public function build(): static
    {
        parent::build();

        $company = $this->customer->company_name ?: $this->user->name;

        if ($this->user->email !== '') {
            $this->replyTo($this->user->email, $this->user->name ?: (string) $company);
        }

        return $this;
    }
}
