<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post as ApiPost;
use App\ApiResource\Processor\PageWriteProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Pko\LunarMediaCore\Concerns\HasMediaAttachments;
use Pko\PageBuilder\Services\PageBuilderManager;

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
#[ApiResource(operations: [
    new GetCollection,
    new Get,
    // Écriture réservée au staff (middleware global auth:staff d'API Platform) :
    // créer, modifier et publier une page passent par le même /api/posts que le
    // reste du dashboard. Le PageWriteProcessor whiteliste + normalise.
    new ApiPost(processor: PageWriteProcessor::class),
    new Patch(processor: PageWriteProcessor::class),
])]
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
            // Filtrage published-only pour l'API publique. Exempté pour un staff
            // authentifié : les opérations d'écriture API Platform (créer/éditer/
            // publier une page) doivent pouvoir accéder aux brouillons.
            if (request()->is('api/*') && ! optional(auth('staff'))->check()) {
                $query->where('status', 'published')
                    ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
            }
        });

        // Défense en profondeur : toute écriture stocke un `content` normalisé,
        // quelle que soit la voie (API Platform, éditeur Filament, tools).
        static::saving(function (Post $post): void {
            if (is_array($post->content)) {
                $post->content = PageBuilderManager::normalize($post->content);
            }
        });

        // Flush du cache "derniers articles" de la home, quelle que soit la voie
        // de sauvegarde (form Filament ou éditeur page-builder unifié).
        $flush = static fn () => Cache::forget('pko.home.posts.v1');
        static::saved($flush);
        static::deleted($flush);
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
