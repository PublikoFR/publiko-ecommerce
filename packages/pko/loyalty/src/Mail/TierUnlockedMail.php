<?php

declare(strict_types=1);

namespace Pko\Loyalty\Mail;

use Lunar\Models\Customer;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * E-mail 17 « Nouveau palier de fidélité ».
 *
 * `tier_benefit` reprend l'intitulé du cadeau du palier, et sa description
 * quand elle existe : le texte client ne prévoit qu'un seul emplacement.
 */
class TierUnlockedMail extends TemplatedMail
{
    public function __construct(
        public readonly ?Customer $customer,
        public readonly LoyaltyTier $tier,
        public readonly int $totalPoints,
    ) {
        parent::__construct('loyalty.tier_unlocked', [
            'first_name' => (string) ($customer?->first_name ?? ''),
            'tier_name' => (string) $tier->name,
            'tier_benefit' => self::benefit($tier),
            'total_points' => (string) $totalPoints,
        ]);
    }

    public static function benefit(LoyaltyTier $tier): string
    {
        $benefit = (string) $tier->gift_title;

        if (! empty($tier->gift_description)) {
            $benefit .= ' — '.$tier->gift_description;
        }

        return $benefit;
    }
}
