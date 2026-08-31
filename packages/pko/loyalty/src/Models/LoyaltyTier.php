<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Pko\LunarMediaCore\Concerns\HasMediaAttachments;

class LoyaltyTier extends Model
{
    use HasMediaAttachments;

    protected $table = 'pko_loyalty_tiers';

    protected $guarded = [];

    protected $casts = [
        'points_required' => 'integer',
        'position' => 'integer',
        'active' => 'boolean',
    ];

    public function getGiftImageUrlAttribute(): ?string
    {
        return $this->firstMediaUrl('gift_image');
    }
}
