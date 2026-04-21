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
class HomeTile extends Model
{
    use HasMediaAttachments;

    protected $table = 'pko_home_tiles';

    protected $fillable = ['title', 'subtitle', 'cta_label', 'cta_url', 'position', 'is_active'];

    protected $casts = ['is_active' => 'bool', 'position' => 'integer'];

    /**
     * Defense-in-depth : filtre active-only sur requêtes /api/*.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('pko_api_active_only', function (Builder $query): void {
            if (app()->runningInConsole()) {
                return;
            }
            if (request()->is('api/*')) {
                $query->where('is_active', true);
            }
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->firstMediaUrl('image');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('position');
    }
}
