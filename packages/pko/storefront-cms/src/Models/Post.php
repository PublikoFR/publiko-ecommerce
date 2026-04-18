<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pko\StorefrontCms\Concerns\HasMediaAttachments;

class Post extends Model
{
    use HasMediaAttachments;

    protected $table = 'pko_posts';

    protected $fillable = ['slug', 'title', 'excerpt', 'body', 'status', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->firstMediaUrl('cover');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->where(fn ($q2) => $q2->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('published_at');
    }
}
