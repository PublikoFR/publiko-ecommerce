<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeSlide extends Model
{
    protected $table = 'mde_home_slides';

    protected $fillable = ['title', 'subtitle', 'image_url', 'cta_label', 'cta_url', 'bg_color', 'text_color', 'position', 'is_active', 'starts_at', 'ends_at'];

    protected $casts = ['is_active' => 'bool', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'position' => 'integer'];

    public function scopeActive(Builder $q): Builder
    {
        $now = now();

        return $q->where('is_active', true)
            ->where(fn ($q2) => $q2->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q2) => $q2->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('position');
    }
}
