<?php

declare(strict_types=1);

namespace Mde\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'mde_loyalty_settings';

    protected $guarded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
