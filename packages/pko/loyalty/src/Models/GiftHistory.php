<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Pko\Loyalty\Enums\GiftStatus;

class GiftHistory extends Model
{
    protected $table = 'pko_loyalty_gift_history';

    protected $guarded = [];

    protected $casts = [
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
}
