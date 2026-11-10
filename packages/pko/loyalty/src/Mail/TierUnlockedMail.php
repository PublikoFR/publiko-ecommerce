<?php

declare(strict_types=1);

namespace Pko\Loyalty\Mail;

use Lunar\Models\Customer;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * E-mail 17 « Nouveau palier de fidélité ».
 *
 * `tier_benefit` et `gift_title` portent l'intitulé du cadeau typé du palier
 * (« Bon d'achat de 50 € HT », cf. `LoyaltyTier::giftLabel()`). Le cadeau libre
 * (description, photo) n'existe plus : `gift_description` et `gift_image_url`
 * restent déclarés pour les contenus déjà en base, mais sont vides — l'encart
 * cadeau du message se replie alors sur l'intitulé seul.
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
            'gift_title' => $tier->giftLabel(),
            'gift_description' => '',
            'gift_image_url' => '',
        ]);
    }

    public static function benefit(LoyaltyTier $tier): string
    {
        return $tier->giftLabel();
    }
}
