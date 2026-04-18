<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lunar\Models\Product;

/**
 * @property int $id
 * @property int $feature_family_id
 * @property string $handle
 * @property string $name
 * @property int $position
 * @property array|null $meta
 */
class FeatureValue extends Model
{
    protected $table = 'pko_feature_values';

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
        'meta' => 'array',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(FeatureFamily::class, 'feature_family_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'pko_feature_value_product',
            'feature_value_id',
            'product_id',
        );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
