<?php

declare(strict_types=1);

namespace Pko\Loyalty\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Lunar\Models\Customer;
use Pko\Loyalty\Models\LoyaltyTier;

class TierUnlockedCustomer extends Notification
{
    use Queueable;

    public function __construct(
        public ?Customer $customer,
        public LoyaltyTier $tier,
        public int $totalPoints,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstname = $this->customer?->first_name ?? '';

        return (new MailMessage)
            ->subject(__('loyalty::mail.customer_subject'))
            ->greeting(__('loyalty::mail.customer_greeting', ['firstname' => $firstname]))
            ->line(__('loyalty::mail.customer_intro', ['tier' => $this->tier->name]))
            ->line('🎁 '.$this->tier->gift_title)
            ->when($this->tier->gift_description, fn (MailMessage $m) => $m->line($this->tier->gift_description))
            ->line(__('loyalty::mail.total_points', ['points' => $this->totalPoints]))
            ->line(__('loyalty::mail.keep_going'));
    }
}
