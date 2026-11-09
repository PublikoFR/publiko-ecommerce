<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Support;

use Lunar\Models\Brand;

/**
 * Référence boutique d'un produit = préfixe de la marque + référence fabricant.
 *
 * Ex. Somfy (préfixe SOM) + MPN « 1240385 » → `SOM1240385`.
 *
 * Point de composition UNIQUE (import, commande de recalcul…). La référence
 * fabricant est concaténée BRUTE : ni trim, ni majuscules, ni suppression des
 * zéros de tête ou des caractères spéciaux — le MPN stocké reste la source.
 */
final class ProductReference
{
    /**
     * Retourne null si la marque n'a pas de préfixe ou si le MPN est vide
     * (vide = chaîne vide ou uniquement des espaces).
     */
    public static function compose(?Brand $brand, int|string|null $mpn): ?string
    {
        return self::composeFromPrefix($brand?->pko_ref_prefix, $mpn);
    }

    public static function composeFromPrefix(?string $prefix, int|string|null $mpn): ?string
    {
        if ($prefix === null || $prefix === '' || $mpn === null) {
            return null;
        }

        $mpn = (string) $mpn;
        if (trim($mpn) === '') {
            return null;
        }

        return $prefix.$mpn;
    }
}
