<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Support;

use Illuminate\Support\Str;
use Lunar\Models\Brand;

/**
 * Préfixe de référence d'une marque (`lunar_brands.pko_ref_prefix`).
 *
 * Format : 2 à 5 caractères alphanumériques MAJUSCULES (défaut : 3 lettres).
 * Proposition par défaut : les 3 premières lettres alphabétiques du nom, sans
 * accents, en majuscules (« Somfy » → SOM, « Électro Pro » → ELE).
 * Le préfixe est unique : une collision est refusée, l'admin arbitre parmi
 * les alternatives proposées par {@see alternatives()}.
 */
final class BrandReferencePrefix
{
    public const PATTERN = '/^[A-Z0-9]{2,5}$/';

    public const DEFAULT_LENGTH = 3;

    /** Normalise une saisie admin (espaces de bord retirés, majuscules). */
    public static function normalize(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }

    public static function isValid(?string $prefix): bool
    {
        return $prefix !== null && preg_match(self::PATTERN, $prefix) === 1;
    }

    /** Lettres A-Z du nom, translittérées (accents retirés), en majuscules. */
    private static function letters(string $name): string
    {
        return (string) preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name)));
    }

    /**
     * Préfixe par défaut : 3 premières lettres du nom. Null si le nom compte
     * moins de 2 lettres (aucune proposition valide possible).
     */
    public static function suggest(?string $name): ?string
    {
        $prefix = substr(self::letters((string) $name), 0, self::DEFAULT_LENGTH);

        return self::isValid($prefix) ? $prefix : null;
    }

    /** Marque qui détient déjà ce préfixe (hors `$ignoreBrandId`), ou null. */
    public static function owner(string $prefix, ?int $ignoreBrandId = null): ?Brand
    {
        return Brand::query()
            ->where('pko_ref_prefix', $prefix)
            ->when($ignoreBrandId !== null, fn ($q) => $q->whereKeyNot($ignoreBrandId))
            ->first();
    }

    public static function isTaken(string $prefix, ?int $ignoreBrandId = null): bool
    {
        return self::owner($prefix, $ignoreBrandId) !== null;
    }

    /**
     * Proposition par défaut si elle est libre, sinon null.
     */
    public static function freeSuggestion(?string $name, ?int $ignoreBrandId = null): ?string
    {
        $prefix = self::suggest($name);

        return $prefix !== null && ! self::isTaken($prefix, $ignoreBrandId) ? $prefix : null;
    }

    /**
     * Alternatives libres au préfixe par défaut, dans l'ordre de préférence :
     * 4 puis 5 premières lettres, 2 premières + une lettre suivante, initiale +
     * consonnes, puis préfixe par défaut suivi d'un chiffre.
     *
     * @param  array<int, string>  $exclude  préfixes à écarter en plus de ceux déjà pris
     * @return array<int, string>
     */
    public static function alternatives(?string $name, ?int $ignoreBrandId = null, int $limit = 3, array $exclude = []): array
    {
        $letters = self::letters((string) $name);
        if (strlen($letters) < 2) {
            return [];
        }

        $base = substr($letters, 0, self::DEFAULT_LENGTH);
        $candidates = [substr($letters, 0, 4), substr($letters, 0, 5)];

        for ($i = 2, $n = strlen($letters); $i < $n; $i++) {
            $candidates[] = substr($letters, 0, 2).$letters[$i];
        }

        $consonants = (string) preg_replace('/[AEIOUY]/', '', substr($letters, 1));
        for ($i = 0, $n = strlen($consonants); $i + 1 < $n; $i++) {
            $candidates[] = $letters[0].$consonants[$i].$consonants[$i + 1];
        }

        for ($d = 2; $d <= 9; $d++) {
            $candidates[] = $base.$d;
        }

        $excluded = array_flip([$base, ...$exclude]);
        $out = [];
        foreach (array_unique($candidates) as $candidate) {
            if (count($out) >= $limit) {
                break;
            }
            if (! self::isValid($candidate) || isset($excluded[$candidate]) || self::isTaken($candidate, $ignoreBrandId)) {
                continue;
            }
            $out[] = $candidate;
        }

        return $out;
    }

    /**
     * Message de refus d'une collision, avec alternatives pour l'arbitrage.
     */
    public static function collisionMessage(string $prefix, Brand $owner, ?string $name, ?int $ignoreBrandId = null): string
    {
        $alternatives = self::alternatives($name, $ignoreBrandId, 3, [$prefix]);

        return "Le préfixe « {$prefix} » est déjà utilisé par la marque « {$owner->name} »."
            .($alternatives !== [] ? ' Suggestions : '.implode(', ', $alternatives).'.' : ' Choisissez un autre préfixe (2 à 5 caractères).');
    }
}
