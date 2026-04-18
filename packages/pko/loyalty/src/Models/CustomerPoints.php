<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Customer;

class CustomerPoints extends Model
{
    protected $table = 'pko_loyalty_customer_points';

    protected $guarded = [];

    protected $casts = [
        'total_points' => 'integer',
        'last_order_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currentTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'current_tier_id');
    }
}
