<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Support;

/**
 * Destinataire de la notification équipe : l'e-mail de contact de la boutique
 * (Storefront → Paramètres → Contact), comme le formulaire /contact. Repli sur
 * l'e-mail des notifications internes, puis sur l'expéditeur par défaut.
 */
final class QuoteRequestRecipient
{
    public static function email(): string
    {
        $candidates = [
            function_exists('brand_setting')
                ? brand_setting('contact.email', config('storefront.contact.email'))
                : config('storefront.contact.email'),
            function_exists('admin_notification_email') ? admin_notification_email() : null,
            config('mail.from.address'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }
}
