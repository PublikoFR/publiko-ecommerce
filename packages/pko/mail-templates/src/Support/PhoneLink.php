<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

/**
 * Construit un `tel:` cliquable depuis un numéro saisi (FR ou international).
 */
final class PhoneLink
{
    public static function href(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '33'.substr($digits, 1);
        }

        return 'tel:+'.$digits;
    }
}
