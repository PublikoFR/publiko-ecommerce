<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mde\StorefrontCms\Concerns\HasMediaAttachments;

class HomeTile extends Model
{
    use HasMediaAttachments;

    protected $table = 'mde_home_tiles';

    protected $fillable = ['title', 'subtitle', 'cta_label', 'cta_url', 'position', 'is_active'];

    protected $casts = ['is_active' => 'bool', 'position' => 'integer'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->firstMediaUrl('image');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('position');
    }
}
