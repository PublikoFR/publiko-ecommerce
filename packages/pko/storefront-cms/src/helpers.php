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

if (! function_exists('brand_media_url')) {
    /**
     * Résout une valeur de Setting média (logo/favicon) en URL affichable :
     * URL absolue ou chemin public ('/img/...') tel quel, sinon URL du disque
     * public (upload Filament). Retourne null si vide.
     */
    function brand_media_url(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (Str::startsWith($raw, ['http://', 'https://', '/'])) {
            return $raw;
        }

        try {
            return Storage::disk('public')->url($raw);
        } catch (Throwable) {
            return '/'.ltrim($raw, '/');
        }
    }
}

if (! function_exists('brand_logo')) {
    /**
     * URL du logo (thème clair) de la boutique (Setting brand.logo), ou null
     * pour retomber sur le logo textuel de repli.
     */
    function brand_logo(): ?string
    {
        return brand_media_url(brand_setting('brand.logo'));
    }
}

if (! function_exists('brand_logo_dark')) {
    /**
     * URL du logo pour le thème sombre (Setting brand.logo_dark), ou null.
     */
    function brand_logo_dark(): ?string
    {
        return brand_media_url(brand_setting('brand.logo_dark'));
    }
}

if (! function_exists('brand_favicon')) {
    /**
     * URL du favicon de la boutique (Setting brand.favicon), commun aux deux
     * thèmes, ou null pour retomber sur le favicon par défaut.
     */
    function brand_favicon(): ?string
    {
        return brand_media_url(brand_setting('brand.favicon'));
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

if (! function_exists('admin_notification_email')) {
    /**
     * Destinataire des e-mails internes (inscription, commande, virement, palier).
     *
     * Source : Storefront → Paramètres (`admin_email`), puis les env historiques
     * `ADMIN_NOTIFICATION_EMAIL` / `CONTACT_EMAIL` / `LOYALTY_ADMIN_EMAIL`.
     */
    function admin_notification_email(): string
    {
        $fromSetting = brand_setting('admin_email');

        if (is_array($fromSetting)) {
            $fromSetting = collect($fromSetting)->filter(fn (mixed $v): bool => is_string($v) && $v !== '')->first();
        }

        if (is_string($fromSetting) && $fromSetting !== '') {
            return $fromSetting;
        }

        foreach ([
            config('customer-auth.admin_notification_email'),
            config('loyalty.admin_email'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
