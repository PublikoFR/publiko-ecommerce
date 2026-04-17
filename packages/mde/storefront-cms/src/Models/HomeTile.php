<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeTile extends Model
{
    protected $table = 'mde_home_tiles';

    protected $fillable = ['title', 'subtitle', 'image_url', 'cta_label', 'cta_url', 'position', 'is_active'];

    protected $casts = ['is_active' => 'bool', 'position' => 'integer'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('position');
    }
}
