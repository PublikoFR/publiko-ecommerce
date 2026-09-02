<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use Illuminate\Support\Facades\Config;

/**
 * Aligne l'expéditeur des e-mails sur l'identité de boutique configurée en
 * back-office, plutôt que sur `MAIL_FROM_*` figé dans le `.env`.
 *
 * Sans ça, réutiliser le back-office sur une autre boutique impose une édition
 * du `.env` et un redéploiement pour une donnée qui est éditable à l'écran.
 */
final class BrandMailFrom
{
    public static function apply(): void
    {
        $name = self::setting('brand.name') ?: config('mail.from.name');
        $address = self::setting('contact.email_sender')
            ?: self::setting('contact.email')
            ?: config('mail.from.address');

        if (is_string($address) && $address !== '') {
            Config::set('mail.from.address', $address);
        }

        if (is_string($name) && $name !== '') {
            Config::set('mail.from.name', $name);
        }
    }

    private static function setting(string $key): ?string
    {
        // Le package ne dépend pas de storefront-cms : s'il est absent (ou si la
        // table n'est pas encore migrée), on garde simplement la valeur du .env.
        if (! function_exists('brand_setting')) {
            return null;
        }

        try {
            $value = brand_setting($key);
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
