<?php

declare(strict_types=1);

namespace Mde\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    protected $table = 'mde_loyalty_tiers';

    protected $guarded = [];

    protected $casts = [
        'points_required' => 'integer',
        'position' => 'integer',
        'active' => 'boolean',
    ];
}
