<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Pko\LunarMediaCore\Concerns\HasMediaAttachments;

/**
 * @property int $id
 * @property int $post_type_id
 * @property string $slug
 * @property string $title
 * @property ?string $cover_url
 * @property ?string $excerpt
 * @property ?string $body
 * @property ?array $content
 * @property ?string $seo_title
 * @property ?string $seo_description
 * @property string $status
 * @property ?Carbon $published_at
 */
#[ApiResource(operations: [new GetCollection, new Get])]
class Post extends Model
{
    use HasMediaAttachments;

    protected $table = 'pko_posts';

    protected $fillable = [
        'post_type_id',
        'slug',
        'title',
        'excerpt',
        'body',
        'content',
        'seo_title',
        'seo_description',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'content' => 'array',
    ];

    /**
     * Defense-in-depth : même si l'API est auth-gatée, on force le filtrage
     * status=published+published_at<=now() sur toute requête /api/*.
     * Évite la fuite de drafts/scheduled si le middleware auth change.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('pko_api_published_only', function (Builder $query): void {
            if (app()->runningInConsole()) {
                return;
            }
            if (request()->is('api/*')) {
                $query->where('status', 'published')
                    ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
            }
        });
    }

    public function postType(): BelongsTo
    {
        return $this->belongsTo(PostType::class);
    }

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

    public function scopeOfType(Builder $q, string $handle): Builder
    {
        return $q->whereHas('postType', fn ($qt) => $qt->where('handle', $handle));
    }
}
