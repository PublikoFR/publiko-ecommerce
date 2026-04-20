<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pko\StorefrontCms\Concerns\HasMediaAttachments;

#[ApiResource(operations: [new GetCollection, new Get])]
class HomeOffer extends Model
{
    use HasMediaAttachments;

    protected $table = 'pko_home_offers';

    protected $fillable = ['title', 'subtitle', 'cta_label', 'cta_url', 'badge', 'position', 'is_active', 'ends_at'];

    protected $casts = ['is_active' => 'bool', 'ends_at' => 'datetime', 'position' => 'integer'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->firstMediaUrl('image');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q2) => $q2->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('position');
    }
}
