<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Support\Money;

/**
 * Palier de fidélité. Le cadeau est typé (`gift_type`) et chiffré en HT, en
 * cents : `voucher_amount` (bon d'achat), `franco_threshold` (seuil de franco
 * abaissé), `bfa_fixed_amount` + `bfa_percent` (BFA).
 */
class LoyaltyTier extends Model
{
    protected $table = 'pko_loyalty_tiers';

    protected $guarded = [];

    protected $casts = [
        'points_required' => 'integer',
        'position' => 'integer',
        'active' => 'boolean',
        'gift_type' => GiftType::class,
        'voucher_amount' => 'integer',
        'franco_threshold' => 'integer',
        'bfa_fixed_amount' => 'integer',
        'bfa_percent' => 'decimal:2',
    ];

    protected $attributes = [
        'gift_type' => 'voucher',
    ];

    public function isVoucher(): bool
    {
        return $this->gift_type === GiftType::Voucher;
    }

    /**
     * Intitulé lisible du cadeau, en HT (« Bon d'achat de 50 € HT »).
     */
    public function giftLabel(): string
    {
        return match ($this->gift_type ?? GiftType::Voucher) {
            GiftType::Voucher => $this->voucher_amount
                ? "Bon d'achat de ".Money::format((int) $this->voucher_amount).' HT'
                : "Bon d'achat",
            GiftType::Franco => $this->franco_threshold !== null
                ? 'Franco de port dès '.Money::format((int) $this->franco_threshold).' HT'
                : 'Franco de port abaissé',
            GiftType::Bfa => 'BFA'.($this->bfaDetail() !== '' ? ' : '.$this->bfaDetail() : ''),
        };
    }

    /** « 100 € HT + 2 % », « 2 % », « 100 € HT » ou chaîne vide. */
    public function bfaDetail(): string
    {
        $parts = [];

        if ((int) $this->bfa_fixed_amount > 0) {
            $parts[] = Money::format((int) $this->bfa_fixed_amount).' HT';
        }

        if ($this->bfa_percent !== null && (float) $this->bfa_percent > 0) {
            $parts[] = rtrim(rtrim(str_replace('.', ',', (string) $this->bfa_percent), '0'), ',').' %';
        }

        return implode(' + ', $parts);
    }
}
