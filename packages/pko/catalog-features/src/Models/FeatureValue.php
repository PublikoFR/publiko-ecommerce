<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Models;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
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
#[ApiResource(operations: [new GetCollection, new Get])]
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
