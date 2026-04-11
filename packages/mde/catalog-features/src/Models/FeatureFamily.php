<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Collection as LunarCollection;

/**
 * @property int $id
 * @property string $handle
 * @property string $name
 * @property int $position
 * @property bool $multi_value
 * @property bool $searchable
 */
class FeatureFamily extends Model
{
    protected $table = 'mde_feature_families';

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
        'multi_value' => 'boolean',
        'searchable' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(FeatureValue::class)->orderBy('position');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            LunarCollection::class,
            'mde_feature_family_collection',
            'feature_family_id',
            'collection_id',
        );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereDoesntHave('collections');
    }

    public function scopeForCollection(Builder $query, int $collectionId): Builder
    {
        return $query->where(function (Builder $q) use ($collectionId): void {
            $q->whereDoesntHave('collections')
                ->orWhereHas('collections', fn (Builder $c) => $c->where('collection_id', $collectionId));
        });
    }
}
