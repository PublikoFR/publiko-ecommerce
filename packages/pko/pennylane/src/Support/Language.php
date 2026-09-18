<?php

declare(strict_types=1);

namespace Pko\Pennylane\Support;

/**
 * L'API v2 n'accepte que les locales complètes (`fr_FR`, `en_GB`…), alors que
 * la config historique porte un code court (`PENNYLANE_LANG=fr`).
 */
final class Language
{
    public static function toPennylane(string $language): string
    {
        if (str_contains($language, '_')) {
            return $language;
        }

        return match (strtolower($language)) {
            'en' => 'en_GB',
            'de' => 'de_DE',
            'es' => 'es_ES',
            default => 'fr_FR',
        };
    }
}
