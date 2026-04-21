<?php

declare(strict_types=1);

namespace Pko\StoreLocator\Models;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ApiResource(operations: [new GetCollection, new Get])]
class Store extends Model
{
    protected $table = 'pko_stores';

    protected $fillable = ['slug', 'name', 'address_line_1', 'address_line_2', 'postcode', 'city', 'country_iso2', 'lat', 'lng', 'phone', 'email', 'hours', 'is_active'];

    protected $casts = ['hours' => 'array', 'is_active' => 'bool', 'lat' => 'float', 'lng' => 'float'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('name');
    }
}
