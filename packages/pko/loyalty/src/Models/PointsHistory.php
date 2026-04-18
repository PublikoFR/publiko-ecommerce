<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Customer;
use Lunar\Models\Order;

class PointsHistory extends Model
{
    protected $table = 'pko_loyalty_points_history';

    protected $guarded = [];

    protected $casts = [
        'points_earned' => 'integer',
        'order_total_ht' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
