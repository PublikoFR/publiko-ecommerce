<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

/**
 * Parsing numérique tolérant des valeurs issues d'un fichier fournisseur.
 *
 * Point d'entrée unique pour tout champ numérique du writer (prix, stock,
 * quantités, dimensions). Distingue trois cas, parce que le writer doit les
 * traiter différemment :
 *
 *  - valeur vide (`null`, `''`, espaces) → `isBlank()` : la clé est ignorée,
 *    jamais convertie en 0 (sinon un prix ou un stock existant serait écrasé) ;
 *  - valeur parsable → `toFloat()` renvoie le nombre ;
 *  - valeur non vide mais non parsable (« N/C », « sur devis ») → `toFloat()`
 *    renvoie null : le writer ignore la clé et logge un avertissement.
 *
 * Formats acceptés : `12.50`, `12,50` (virgule décimale FR), `1 234,50`
 * (espaces, espaces insécables et fines comme séparateurs de milliers),
 * `1.234,50`, `1,234.50`, symbole `€` en tête ou en fin.
 */
final class NumberParser
{
    public static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    public static function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        // Espaces (dont insécable U+00A0 et fine U+202F) et symbole euro.
        $s = (string) preg_replace('/[\s\x{00A0}\x{202F}€]+/u', '', $value);
        if ($s === '') {
            return null;
        }

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Les deux séparateurs : le dernier est la décimale.
            $s = $lastComma > $lastDot
                ? str_replace(['.', ','], ['', '.'], $s)
                : str_replace(',', '', $s);
        } elseif ($lastComma !== false) {
            // Virgule seule = décimale FR ; plusieurs virgules = ambigu, refusé.
            if (substr_count($s, ',') > 1) {
                return null;
            }
            $s = str_replace(',', '.', $s);
        } elseif ($lastDot !== false && substr_count($s, '.') > 1) {
            // « 1.234.567 » : points = séparateurs de milliers.
            $s = str_replace('.', '', $s);
        }

        if (! preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $s)) {
            return null;
        }

        return (float) $s;
    }

    /** Montant en euros → cents entiers (arrondi), null si vide ou non parsable. */
    public static function eurosToCents(mixed $value): ?int
    {
        $euros = self::toFloat($value);

        return $euros === null ? null : (int) round($euros * 100);
    }

    /** Entier arrondi, null si vide ou non parsable. */
    public static function toInt(mixed $value): ?int
    {
        $number = self::toFloat($value);

        return $number === null ? null : (int) round($number);
    }
}
