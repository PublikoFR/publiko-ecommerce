<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Customer;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Pko\Loyalty\Enums\GiftStatus;
use Pko\Loyalty\Services\LoyaltyVoucherService;

class GiftHistory extends Model
{
    protected $table = 'pko_loyalty_gift_history';

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'points_at_unlock' => 'integer',
        'admin_viewed' => 'boolean',
        'email_sent' => 'boolean',
        'unlocked_at' => 'datetime',
        'status_updated_at' => 'datetime',
        'status' => GiftStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'tier_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Bon d'achat Lunar émis pour ce déblocage (palier « bon d'achat »). */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * Bon d'achat prêt à afficher, ou null si aucun bon n'a été émis.
     *
     * @return array{code: string, amount: int, expires_at: Carbon|null, status: string, status_label: string}|null
     */
    public function voucher(): ?array
    {
        $discount = $this->discount;

        if ($discount === null) {
            return null;
        }

        $status = LoyaltyVoucherService::status($discount);

        return [
            'code' => (string) $discount->coupon,
            'amount' => LoyaltyVoucherService::amount($discount),
            'expires_at' => $discount->ends_at,
            'status' => $status,
            'status_label' => LoyaltyVoucherService::statusLabel($status),
        ];
    }
}
