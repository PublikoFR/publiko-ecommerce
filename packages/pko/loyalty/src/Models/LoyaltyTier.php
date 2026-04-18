<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    protected $table = 'pko_loyalty_tiers';

    protected $guarded = [];

    protected $casts = [
        'points_required' => 'integer',
        'position' => 'integer',
        'active' => 'boolean',
    ];
}
