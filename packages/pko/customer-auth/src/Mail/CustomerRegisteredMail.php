<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Customer;

class CustomerRegisteredMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly User $user,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Confirmation de votre inscription — '.brand_name())
            ->view('customer-auth::mail.customer-registered');
    }
}
