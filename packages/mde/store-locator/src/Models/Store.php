<?php

declare(strict_types=1);

namespace Mde\StoreLocator\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $table = 'mde_stores';

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
