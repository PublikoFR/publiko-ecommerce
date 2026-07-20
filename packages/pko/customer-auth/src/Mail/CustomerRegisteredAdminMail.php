<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Customer;

/**
 * Notification interne envoyée à l'administrateur à chaque inscription client,
 * récapitulant toutes les coordonnées saisies.
 */
class CustomerRegisteredAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly User $user,
    ) {}

    public function build(): static
    {
        $company = $this->customer->company_name ?: $this->user->name;

        return $this
            ->subject('Nouvelle inscription client — '.$company)
            ->replyTo($this->user->email, $this->user->name ?: $company)
            ->view('customer-auth::mail.customer-registered-admin');
    }
}
