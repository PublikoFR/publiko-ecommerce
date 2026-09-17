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
 * quand elle existe, en une seule phrase. `gift_title`, `gift_description` et
 * `gift_image_url` servent à l'encart cadeau du message.
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
            'gift_title' => (string) $tier->gift_title,
            'gift_description' => (string) ($tier->gift_description ?? ''),
            'gift_image_url' => self::giftImageUrl($tier),
        ]);
    }

    /**
     * URL absolue : un client mail ne résout pas un chemin relatif au site.
     */
    public static function giftImageUrl(LoyaltyTier $tier): string
    {
        return (string) $tier->firstMedia('gift_image')?->getFullUrl();
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
