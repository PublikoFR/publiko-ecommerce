<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Settings;

use Pko\StorefrontCms\Models\Setting;
use Throwable;

/**
 * Source unique de vérité pour les paramètres d'expédition.
 *
 * Résolution DB-first : DB (pko_storefront_settings) → config() → défaut codé.
 * Toujours utiliser ce helper plutôt que config('shipping.*') ou Setting::get()
 * directement dans les consommateurs.
 */
final class ShippingSettings
{
    /**
     * Seuil franco de port en cents HT (50 000 = 500,00 € HT).
     *
     * Clé DB    : shipping.franco.threshold_cents
     * Fallback  : config('shipping.franco.threshold_ht_cents') → 50 000
     */
    public static function thresholdCents(): int
    {
        $stored = self::dbGet('shipping.franco.threshold_cents');

        if ($stored !== null) {
            return (int) $stored;
        }

        return (int) (config('shipping.franco.threshold_ht_cents') ?? 50000);
    }

    /**
     * Codes de service éligibles au franco (codes nus, ex : ['chrono13']).
     *
     * Clé DB    : shipping.franco.services
     * Fallback  : ['chrono13']
     *
     * @return list<string>
     */
    public static function francoServices(): array
    {
        $stored = self::dbGet('shipping.franco.services');

        if (is_array($stored) && $stored !== []) {
            return array_values(array_filter(array_map('strval', $stored)));
        }

        return ['chrono13'];
    }

    /**
     * Base de calcul du franco : 'eligible_only' | 'cart_total'.
     *
     * - eligible_only : seules les lignes franco-éligibles comptent ;
     *                   une ligne exclue bloque le franco.
     * - cart_total    : toutes les lignes comptent, aucune ligne n'est bloquante.
     *
     * Clé DB    : shipping.franco.basis
     * Fallback  : 'eligible_only'
     */
    public static function francoBasis(): string
    {
        $stored = self::dbGet('shipping.franco.basis');

        if (is_string($stored) && in_array($stored, ['eligible_only', 'cart_total'], true)) {
            return $stored;
        }

        return 'eligible_only';
    }

    /**
     * Base de taxe pour les prix de grille : 'ht' | 'ttc'.
     *
     * Clé DB    : shipping.tax.price_base
     * Fallback  : config('shipping.tax.price_base') → 'ht'
     */
    public static function taxPriceBase(): string
    {
        $stored = self::dbGet('shipping.tax.price_base');

        if (is_string($stored) && in_array($stored, ['ht', 'ttc'], true)) {
            return $stored;
        }

        $cfg = (string) config('shipping.tax.price_base', 'ht');

        return in_array($cfg, ['ht', 'ttc'], true) ? $cfg : 'ht';
    }

    /**
     * Mode d'affichage des prix d'expédition : 'both' | 'ht' | 'ttc'.
     *
     * Clé DB    : shipping.tax.display
     * Fallback  : config('shipping.tax.display') → 'both'
     */
    public static function taxDisplay(): string
    {
        $stored = self::dbGet('shipping.tax.display');

        if (is_string($stored) && in_array($stored, ['both', 'ht', 'ttc'], true)) {
            return $stored;
        }

        $cfg = (string) config('shipping.tax.display', 'both');

        return in_array($cfg, ['both', 'ht', 'ttc'], true) ? $cfg : 'both';
    }

    private static function dbGet(string $key): mixed
    {
        try {
            return Setting::get($key);
        } catch (Throwable) {
            return null;
        }
    }
}
