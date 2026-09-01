<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Customer;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * E-mail 18 « Anniversaire de fidélité ».
 */
class LoyaltyAnniversaryMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(
        public readonly Customer $customer,
        public readonly int $years,
    ) {
        parent::__construct('loyalty.anniversary', [
            'first_name' => (string) ($customer->first_name ?? ''),
            'duration_label' => $years > 1 ? $years.' ans' : '1 an',
        ]);
    }
}
