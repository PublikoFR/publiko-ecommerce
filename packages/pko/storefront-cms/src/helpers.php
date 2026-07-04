<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

if (! function_exists('brand_logo')) {
    /**
     * URL du logo de la boutique (Setting brand.logo), ou null pour retomber
     * sur le logo textuel de repli. Chemin public ('/img/...') ou URL absolue.
     */
    function brand_logo(): ?string
    {
        $logo = brand_setting('brand.logo');

        if (! is_string($logo) || $logo === '') {
            return null;
        }

        // URL absolue ou chemin public direct ('/img/...') → tel quel.
        if (Str::startsWith($logo, ['http://', 'https://', '/'])) {
            return $logo;
        }

        // Sinon chemin relatif sur le disque public (upload Filament) → URL publique.
        try {
            return Storage::disk('public')->url($logo);
        } catch (Throwable) {
            return '/'.ltrim($logo, '/');
        }
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
