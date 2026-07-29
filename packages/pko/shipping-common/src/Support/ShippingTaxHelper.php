<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\Taxes;
use Lunar\Models\TaxClass;

/**
 * Helpers de conversion taxe pour les frais de port.
 *
 * Extrait d'AbstractCarrierModifier pour permettre la réutilisation dans
 * ShippingCalculator et le test unitaire isolé.
 */
final class ShippingTaxHelper
{
    /**
     * Taux de TVA effectif d'une TaxClass pour l'adresse/devise courantes.
     *
     * Sonde le moteur de taxe Lunar sur une base connue (zone-aware, FR métropole
     * en v1). Retourne 0.0 si la zone de taxe n'est pas résolue (tolérant aux pannes).
     */
    public static function effectiveTaxRate(TaxClass $taxClass, mixed $address, mixed $currency): float
    {
        $probeBase = 100000; // 1 000,00 € — base large pour limiter l'erreur d'arrondi.

        $probe = new ShippingOption(
            name: 'tax-probe',
            description: null,
            identifier: 'shipping.tax.probe',
            price: new Price($probeBase, $currency, 1),
            taxClass: $taxClass,
        );

        try {
            $taxCents = (int) Taxes::setShippingAddress($address)
                ->setCurrency($currency)
                ->setPurchasable($probe)
                ->getBreakdown($probeBase)
                ->amounts
                ->sum('price.value');
        } catch (\Throwable) {
            return 0.0;
        }

        return $probeBase > 0 ? $taxCents / $probeBase : 0.0;
    }

    /**
     * Convertit un prix TTC (cents) en prix net (cents) selon le taux fourni.
     */
    public static function grossToNet(int $grossCents, float $taxRate): int
    {
        if ($grossCents === 0 || $taxRate <= 0.0) {
            return $grossCents;
        }

        return (int) round($grossCents / (1 + $taxRate));
    }
}
