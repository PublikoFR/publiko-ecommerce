<?php

declare(strict_types=1);

namespace Pko\Pennylane\Support;

use Pko\Pennylane\Api\Exceptions\PennylaneException;

/**
 * Traduit un taux de TVA en pourcentage vers le code attendu par l'API v2
 * (`vat_rate` est une énumération : 20 % → `FR_200`, 5,5 % → `FR_55`…).
 *
 * Le taux est déduit des montants de la commande et non de `tax_breakdown`,
 * que Lunar laisse vide sur nos commandes : on l'arrondit donc au taux
 * français le plus proche plutôt que d'exiger une égalité stricte.
 */
final class VatRate
{
    /** @var array<string,float> */
    private const FRENCH_RATES = [
        'FR_200' => 20.0,
        'FR_100' => 10.0,
        'FR_85' => 8.5,
        'FR_55' => 5.5,
        'FR_21' => 2.1,
    ];

    /** Écart toléré entre le taux calculé et un taux légal (arrondis au centime). */
    private const TOLERANCE = 0.15;

    public static function toPennylaneCode(float $percentage): string
    {
        if ($percentage < self::TOLERANCE) {
            return 'exempt';
        }

        foreach (self::FRENCH_RATES as $code => $rate) {
            if (abs($percentage - $rate) <= self::TOLERANCE) {
                return $code;
            }
        }

        throw new PennylaneException(sprintf('Taux de TVA %.2f %% sans équivalent Pennylane.', $percentage));
    }

    /**
     * Taux effectif (en %) d'un montant HT et de sa taxe, exprimés en cents.
     */
    public static function fromAmounts(int $taxableCents, int $taxCents): float
    {
        if ($taxableCents <= 0 || $taxCents <= 0) {
            return 0.0;
        }

        return round($taxCents / $taxableCents * 100, 2);
    }
}
