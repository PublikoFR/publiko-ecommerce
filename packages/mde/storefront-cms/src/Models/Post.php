<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'mde_posts';

    protected $fillable = ['slug', 'title', 'cover_url', 'excerpt', 'body', 'status', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->where(fn ($q2) => $q2->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('published_at');
    }
}
