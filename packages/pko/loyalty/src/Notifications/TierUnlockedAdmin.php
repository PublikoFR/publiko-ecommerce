<?php

declare(strict_types=1);

namespace Pko\Loyalty\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Lunar\Models\Customer;
use Pko\Loyalty\Models\LoyaltyTier;

class TierUnlockedAdmin extends Notification
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
        $name = trim(($this->customer?->first_name ?? '').' '.($this->customer?->last_name ?? '')) ?: '#'.($this->customer?->id ?? '?');

        return (new MailMessage)
            ->subject(__('loyalty::mail.admin_subject'))
            ->line(__('loyalty::mail.admin_intro', [
                'customer' => $name,
                'tier' => $this->tier->name,
            ]))
            ->line('🎁 '.$this->tier->gift_title)
            ->line(__('loyalty::mail.total_points', ['points' => $this->totalPoints]));
    }
}
