<?php

declare(strict_types=1);

use Pko\StorefrontCms\Models\Setting;

if (! function_exists('brand_setting')) {
    function brand_setting(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}

if (! function_exists('brand_name')) {
    function brand_name(): string
    {
        return (string) brand_setting('brand.name', config('app.name', ''));
    }
}

if (! function_exists('brand_tagline')) {
    function brand_tagline(): string
    {
        return (string) brand_setting('brand.tagline', '');
    }
}

if (! function_exists('brand_meta_description')) {
    function brand_meta_description(): string
    {
        return (string) brand_setting('brand.meta_description', '');
    }
}
