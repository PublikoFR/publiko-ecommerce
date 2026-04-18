<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'pko_storefront_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('pko.storefront.settings.v1', 3600, fn () => self::all()->pluck('value', 'key')->toArray());

        return $all[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('pko.storefront.settings.v1');
    }

    public static function forget(): void
    {
        Cache::forget('pko.storefront.settings.v1');
    }
}
