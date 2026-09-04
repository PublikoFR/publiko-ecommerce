<?php

declare(strict_types=1);

namespace Pko\Loyalty\Mail;

use Lunar\Models\Customer;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * Notification équipe quand un client débloque un palier de fidélité.
 */
class TierUnlockedAdminMail extends TemplatedMail
{
    public function __construct(
        public readonly ?Customer $customer,
        public readonly LoyaltyTier $tier,
        public readonly int $totalPoints,
    ) {
        $contact = trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? ''));

        parent::__construct('loyalty.tier_unlocked_admin', [
            'company_name' => (string) ($customer?->company_name ?? ''),
            'contact_name' => $contact !== '' ? $contact : '#'.($customer?->id ?? '?'),
            'email' => (string) ($customer?->users()->first()?->email ?? ''),
            'tier_name' => (string) $tier->name,
            'tier_benefit' => TierUnlockedMail::benefit($tier),
            'total_points' => (string) $totalPoints,
            'admin_url' => $customer !== null ? url('/admin/customers/'.$customer->id) : url('/admin'),
        ]);
    }
}
